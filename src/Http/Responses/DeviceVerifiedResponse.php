<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceVerifiedResponse as DeviceVerifiedResponseContract;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceVerifiedResponse implements DeviceVerifiedResponseContract
{
    public function toResponse(Request $request, Device $device): Response
    {
        $redirect = redirect()->intended(DeviceAuth::home());

        if (! $request->expectsJson()) {
            return $redirect;
        }

        return new JsonResponse([
            'status' => $device->status->value,
            'redirect' => $redirect->getTargetUrl(),
        ]);
    }
}
