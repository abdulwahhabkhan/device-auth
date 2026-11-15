<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

/**
 * The review action does not apply to the device's current status
 * (approving an active device, rejecting a revoked one…).
 */
final class InvalidDeviceState extends DeviceAuthException
{
    public function reason(): string
    {
        return 'conflict';
    }

    public function status(): int
    {
        return 409;
    }
}
