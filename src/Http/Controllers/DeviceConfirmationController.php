<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\Contracts\ConfirmsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\ConfirmViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceConfirmedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse;
use AbdulWahhabKhan\DeviceAuth\Data\ConfirmPageData;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-confirm the current device before sensitive actions
 * (the device-key version of Laravel's password confirmation).
 */
class DeviceConfirmationController extends Controller
{
    public function show(Request $request, ConfirmViewResponse $view): Response
    {
        return $view->toResponse($request, ConfirmPageData::fromRequest($request));
    }

    public function status(Request $request): JsonResponse
    {
        return new JsonResponse([
            'confirmed' => DeviceAuth::recentlyConfirmed($request),
        ]);
    }

    public function options(Request $request, BeginsDeviceVerification $action): JsonResponse
    {
        return new JsonResponse($action->begin($request, CeremonyPurpose::Confirm));
    }

    public function store(Request $request, ConfirmsDevice $action, DeviceConfirmedResponse $confirmed, FailedDeviceAuthResponse $failed): Response
    {
        try {
            $device = $action->confirm($request, $request->all());
        } catch (DeviceAuthException $e) {
            return $failed->toResponse($request, $e);
        }

        return $confirmed->toResponse($request, $device);
    }
}
