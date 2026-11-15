<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

/**
 * The key is valid but belongs to another device than expected
 * (confirmation with a different device, or a key approved for another platform).
 */
final class DeviceMismatch extends DeviceAuthException
{
    public function reason(): string
    {
        return 'mismatch';
    }
}
