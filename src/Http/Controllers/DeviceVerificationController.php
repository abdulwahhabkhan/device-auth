<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceVerified;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceMismatch;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\Concerns\RespondsWithDeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Http\Requests\AssertionRequest;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 2 after the password: prove this device holds an approved key.
 */
class DeviceVerificationController extends Controller
{
    use RespondsWithDeviceStatus;

    public function show(Request $request): Response
    {
        if (! DeviceAuth::enabled() || DeviceAuth::currentDevice($request)) {
            return redirect()->intended(config('device-auth.home', '/'));
        }

        $user = $request->user();
        $logout = config('device-auth.logout_route');

        $response = Inertia::render(config('device-auth.pages.verify'), [
            'user' => DeviceAuth::describeUser($user),
            'contact' => config('device-auth.admin_contact'),
            'urls' => [
                'verifyOptions' => route('device-auth.verify.options'),
                'verify' => route('device-auth.verify.store'),
                'requestOptions' => route('device-auth.request.options'),
                'request' => route('device-auth.request.store'),
                'logout' => $logout && Route::has($logout) ? route($logout) : null,
            ],
        ])->toResponse($request);

        // Ask Chromium browsers for device details we show to the administrator.
        $response->headers->set('Accept-CH', 'Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version, Sec-CH-UA-Model, Sec-CH-UA-Mobile');

        return $response;
    }

    public function options(DeviceKeyService $keys): JsonResponse
    {
        return response()->json($keys->beginVerification('verify'));
    }

    public function store(AssertionRequest $request, DeviceKeyService $keys, DeviceMetadata $metadata): JsonResponse
    {
        try {
            $device = $keys->finishVerification('verify', $request->assertion());

            if (config('device-auth.enforce_platform_match', true) && ! $metadata->matchesDevice($request, $device)) {
                Log::warning('Device key used from a different kind of device.', [
                    'device' => $device->getKey(),
                    'expected' => [$device->os_family, $device->device_type],
                    'current' => $metadata->parse($request),
                ]);

                throw new DeviceMismatch('This key was approved for a different device.');
            }
        } catch (DeviceAuthException $e) {
            return $this->failed($e);
        }

        if (! $device->isActive()) {
            return $this->notApproved($device);
        }

        DeviceAuth::markVerified($request, $device);

        DeviceLogin::query()->create([
            'device_id' => $device->getKey(),
            'user_id' => $request->user()->getAuthIdentifier(),
            'ip' => $request->ip(),
            'purpose' => 'verify',
        ]);

        DeviceVerified::dispatch($device, $request->user(), 'verify');

        return response()->json([
            'status' => 'active',
            'redirect' => redirect()->intended(config('device-auth.home', '/'))->getTargetUrl(),
        ]);
    }
}
