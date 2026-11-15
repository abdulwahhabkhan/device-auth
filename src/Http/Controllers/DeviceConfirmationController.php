<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceVerified;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\Concerns\RespondsWithDeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Http\Requests\AssertionRequest;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Re-confirm the current device before sensitive actions
 * (the device-key version of Laravel's password confirmation).
 */
class DeviceConfirmationController extends Controller
{
    use RespondsWithDeviceStatus;

    public function show(Request $request): Response
    {
        $device = DeviceAuth::currentDevice($request);

        return Inertia::render(config('device-auth.pages.confirm'), [
            'device' => $device ? ['name' => $device->name, 'reference' => $device->reference()] : null,
            'urls' => [
                'options' => route('device-auth.confirm.options'),
                'confirm' => route('device-auth.confirm.store'),
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'confirmed' => DeviceAuth::recentlyConfirmed($request),
        ]);
    }

    public function options(Request $request, DeviceKeyService $keys): JsonResponse
    {
        // Only the device this session signed in with may confirm.
        return response()->json($keys->beginVerification('confirm', DeviceAuth::currentDevice($request)));
    }

    public function store(AssertionRequest $request, DeviceKeyService $keys): JsonResponse
    {
        try {
            $device = $keys->finishVerification('confirm', $request->assertion(), DeviceAuth::currentDevice($request));
        } catch (DeviceAuthException $e) {
            return $this->failed($e);
        }

        if (! $device->isActive()) {
            return $this->notApproved($device);
        }

        DeviceAuth::markConfirmed($request);

        DeviceLogin::query()->create([
            'device_id' => $device->getKey(),
            'user_id' => $request->user()->getAuthIdentifier(),
            'ip' => $request->ip(),
            'purpose' => 'confirm',
        ]);

        DeviceVerified::dispatch($device, $request->user(), 'confirm');

        return response()->json([
            'status' => 'confirmed',
            // The full-page flow goes back to where the user was heading; the modal flow stays put.
            'redirect' => $request->boolean('page')
                ? redirect()->intended(config('device-auth.home', '/'))->getTargetUrl()
                : null,
        ]);
    }
}
