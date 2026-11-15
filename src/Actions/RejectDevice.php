<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\RejectsDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRejected;
use AbdulWahhabKhan\DeviceAuth\Exceptions\InvalidDeviceState;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\DeviceRequestReviewed;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;

/**
 * Turn down a pending request. The reason is shown to the person who asked.
 */
class RejectDevice implements RejectsDevice
{
    public function reject(Device $device, ?Authenticatable $reviewer, array $input, ?Device $current = null): Device
    {
        if ($device->status !== DeviceStatus::Pending) {
            throw new InvalidDeviceState(__('device-auth::messages.only_pending_rejectable'));
        }

        /** @var array{review_note: string} $data */
        $data = Validator::make($input, [
            'review_note' => ['required', 'string', 'max:500'],
        ], [
            'review_note.required' => __('device-auth::messages.reject_reason_required'),
        ])->validate();

        $device->forceFill([
            'review_note' => $data['review_note'],
            'status' => DeviceStatus::Rejected,
            'reviewed_by' => $reviewer?->getAuthIdentifier(),
            'reviewed_at' => now(),
        ])->save();

        event(new DeviceRejected($device, $reviewer));

        if (DeviceAuth::notificationChannels() !== [] && $device->requester !== null && method_exists($device->requester, 'notify')) {
            $device->requester->notify(new DeviceRequestReviewed($device));
        }

        return $device;
    }
}
