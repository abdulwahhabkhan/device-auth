<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

use AbdulWahhabKhan\DeviceAuth\Models\Device;

/**
 * A known key whose device is pending, rejected or revoked.
 */
final class DeviceNotApproved extends DeviceAuthException
{
    public function __construct(public readonly Device $device)
    {
        parent::__construct(__('device-auth::messages.not_approved'));
    }

    public function reason(): string
    {
        return $this->device->status->value;
    }

    public function status(): int
    {
        return 403;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->reason(),
            'device' => $this->device->publicSummary(),
            'contact' => config('device-auth.admin_contact'),
        ];
    }
}
