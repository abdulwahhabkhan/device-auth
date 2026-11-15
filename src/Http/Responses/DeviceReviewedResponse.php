<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceReviewedResponse as DeviceReviewedResponseContract;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceReviewedResponse implements DeviceReviewedResponseContract
{
    public function toResponse(Request $request, Device $device, DeviceStatus $decision): Response
    {
        $message = match ($decision) {
            DeviceStatus::Active => __('device-auth::messages.approved', ['name' => $device->name]),
            DeviceStatus::Rejected => __('device-auth::messages.rejected', ['name' => $device->name]),
            DeviceStatus::Revoked, DeviceStatus::Pending => __('device-auth::messages.revoked', ['name' => $device->name]),
        };

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return new JsonResponse([
                'status' => $device->status->value,
                'message' => $message,
                'device' => $device->adminSummary(),
            ]);
        }

        return back()->with(DeviceAuth::SESSION_FLASH, $message);
    }
}
