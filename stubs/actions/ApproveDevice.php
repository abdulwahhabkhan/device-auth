<?php

declare(strict_types=1);

namespace App\Actions\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceApproved;
use AbdulWahhabKhan\DeviceAuth\Exceptions\InvalidDeviceState;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\DeviceRequestReviewed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;

/**
 * Approve a pending, rejected or revoked device: its key may sign in right away.
 */
class ApproveDevice implements ApprovesDevice
{
    public function approve(Device $device, ?Authenticatable $reviewer, array $input, ?Device $current = null): Device
    {
        if ($device->isActive()) {
            throw new InvalidDeviceState(__('device-auth::messages.already_approved'));
        }

        /** @var array{name: string, review_note?: string|null} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $device->forceFill([
            'name' => trim($data['name']),
            'review_note' => $data['review_note'] ?? null,
            'status' => DeviceStatus::Active,
            'reviewed_by' => $reviewer?->getAuthIdentifier(),
            'reviewed_at' => now(),
        ])->save();

        event(new DeviceApproved($device, $reviewer));

        if (DeviceAuth::notificationChannels() !== [] && $device->requester !== null && method_exists($device->requester, 'notify')) {
            $device->requester->notify(new DeviceRequestReviewed($device));
        }

        return $device;
    }
}
