<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

final class TooManyPendingRequests extends DeviceAuthException
{
    public function __construct()
    {
        parent::__construct(__('device-auth::messages.too_many_pending'));
    }

    public function reason(): string
    {
        return 'too_many_requests';
    }

    public function status(): int
    {
        return 429;
    }
}
