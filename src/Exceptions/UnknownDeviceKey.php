<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

/**
 * The browser answered with a key this application has never seen.
 */
final class UnknownDeviceKey extends DeviceAuthException
{
    public function __construct()
    {
        parent::__construct(__('device-auth::messages.unknown_key'));
    }

    public function reason(): string
    {
        return 'not_registered';
    }

    public function status(): int
    {
        return 404;
    }
}
