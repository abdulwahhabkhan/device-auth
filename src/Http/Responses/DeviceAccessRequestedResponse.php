<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceAccessRequestedResponse as DeviceAccessRequestedResponseContract;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceAccessRequestedResponse implements DeviceAccessRequestedResponseContract
{
    public function toResponse(Request $request, Device $device): Response
    {
        return new JsonResponse([
            'status' => $device->status->value,
            'device' => $device->publicSummary(),
            'contact' => DeviceAuth::adminContact(),
        ], 201);
    }
}
