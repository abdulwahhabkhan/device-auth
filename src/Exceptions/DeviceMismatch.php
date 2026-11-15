<?php

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

/**
 * The key is valid but belongs to another device than expected
 * (confirmation with a different device, or a key approved for another platform).
 */
class DeviceMismatch extends DeviceAuthException
{
    public function reason(): string
    {
        return 'mismatch';
    }
}
