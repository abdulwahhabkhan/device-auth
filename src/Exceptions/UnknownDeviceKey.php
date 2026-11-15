<?php

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

/**
 * The browser answered with a key this application has never seen.
 */
class UnknownDeviceKey extends DeviceAuthException
{
    public function __construct()
    {
        parent::__construct('This key is not registered for this application.');
    }

    public function reason(): string
    {
        return 'not_registered';
    }
}
