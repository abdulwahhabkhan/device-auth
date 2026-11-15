<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceRegistration;
use AbdulWahhabKhan\DeviceAuth\Contracts\RequestsDeviceAccess;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceAccessRequestedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Request access for this device": creates a key on the device and stores
 * its public half as pending until an administrator approves it.
 */
class DeviceRequestController extends Controller
{
    public function options(Request $request, BeginsDeviceRegistration $action, FailedDeviceAuthResponse $failed): Response
    {
        try {
            return new JsonResponse($action->begin($request, $request->all()));
        } catch (DeviceAuthException $e) {
            return $failed->toResponse($request, $e);
        }
    }

    public function store(Request $request, RequestsDeviceAccess $action, DeviceAccessRequestedResponse $requested, FailedDeviceAuthResponse $failed): Response
    {
        try {
            $device = $action->request($request, $request->all());
        } catch (DeviceAuthException $e) {
            return $failed->toResponse($request, $e);
        }

        return $requested->toResponse($request, $device);
    }
}
