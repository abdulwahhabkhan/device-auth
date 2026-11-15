<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceConfirmedResponse as DeviceConfirmedResponseContract;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceConfirmedResponse implements DeviceConfirmedResponseContract
{
    public function toResponse(Request $request, Device $device): Response
    {
        // The full-page flow goes back to where the user was heading; the modal flow stays put.
        $redirect = $request->boolean('page') ? redirect()->intended(DeviceAuth::home()) : null;

        if (! $request->expectsJson()) {
            return $redirect ?? back();
        }

        return new JsonResponse([
            'status' => 'confirmed',
            'redirect' => $redirect?->getTargetUrl(),
        ]);
    }
}
