<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceVerifiedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\VerifyViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\VerifiesDevice;
use AbdulWahhabKhan\DeviceAuth\Data\VerifyPageData;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 2 after the password: prove this device holds an approved key.
 */
class DeviceVerificationController extends Controller
{
    public function show(Request $request, VerifyViewResponse $view): Response
    {
        if (! DeviceAuth::enabled() || DeviceAuth::currentDevice($request) instanceof Device) {
            return redirect()->intended(DeviceAuth::home());
        }

        $response = $view->toResponse($request, VerifyPageData::fromRequest($request));

        // Ask Chromium browsers for device details we show to the administrator.
        $response->headers->set('Accept-CH', 'Sec-CH-UA-Platform, Sec-CH-UA-Platform-Version, Sec-CH-UA-Model, Sec-CH-UA-Mobile');

        return $response;
    }

    public function options(Request $request, BeginsDeviceVerification $action): JsonResponse
    {
        return new JsonResponse($action->begin($request, CeremonyPurpose::Verify));
    }

    public function store(Request $request, VerifiesDevice $action, DeviceVerifiedResponse $verified, FailedDeviceAuthResponse $failed): Response
    {
        try {
            $device = $action->verify($request, $request->all());
        } catch (DeviceAuthException $e) {
            return $failed->toResponse($request, $e);
        }

        return $verified->toResponse($request, $device);
    }
}
