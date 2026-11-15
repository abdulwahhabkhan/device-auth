<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers\Concerns;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Exceptions\UnknownDeviceKey;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\JsonResponse;

trait RespondsWithDeviceStatus
{
    protected function failed(DeviceAuthException $e): JsonResponse
    {
        return response()->json([
            'status' => $e->reason(),
            'message' => $e->getMessage(),
        ], $e instanceof UnknownDeviceKey ? 404 : 422);
    }

    /** A known key whose device isn't approved (pending, rejected or revoked). */
    protected function notApproved(Device $device): JsonResponse
    {
        return response()->json([
            'status' => $device->status->value,
            'device' => $device->publicSummary(),
            'contact' => config('device-auth.admin_contact'),
        ], 403);
    }
}
