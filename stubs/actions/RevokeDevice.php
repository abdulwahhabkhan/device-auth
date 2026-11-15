<?php

declare(strict_types=1);

namespace App\Actions\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRevoked;
use AbdulWahhabKhan\DeviceAuth\Exceptions\InvalidDeviceState;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\DeviceRequestReviewed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Withdraw an approved device. It is signed out on its next request.
 */
class RevokeDevice implements RevokesDevice
{
    public function revoke(Device $device, ?Authenticatable $reviewer, array $input, ?Device $current = null): Device
    {
        if (! $device->isActive()) {
            throw new InvalidDeviceState(__('device-auth::messages.only_active_revocable'));
        }

        if ($current?->is($device) === true) {
            throw ValidationException::withMessages([
                'review_note' => __('device-auth::messages.cannot_revoke_current'),
            ]);
        }

        /** @var array{review_note?: string|null} $data */
        $data = Validator::make($input, [
            'review_note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $device->forceFill([
            'review_note' => $data['review_note'] ?? null,
            'status' => DeviceStatus::Revoked,
            'reviewed_by' => $reviewer?->getAuthIdentifier(),
            'reviewed_at' => now(),
        ])->save();

        event(new DeviceRevoked($device, $reviewer));

        if (DeviceAuth::notificationChannels() !== [] && $device->requester !== null && method_exists($device->requester, 'notify')) {
            $device->requester->notify(new DeviceRequestReviewed($device));
        }

        return $device;
    }
}
