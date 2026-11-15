<?php

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

use RuntimeException;

class DeviceAuthException extends RuntimeException
{
    /** Machine-readable reason sent to the frontend. */
    public function reason(): string
    {
        return 'error';
    }
}
