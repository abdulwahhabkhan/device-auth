<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts\Responses;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an administrator approved, rejected or revoked a device.
 */
interface DeviceReviewedResponse
{
    public function toResponse(Request $request, Device $device, DeviceStatus $decision): Response;
}
