<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

final class ChallengeExpired extends DeviceAuthException
{
    public function __construct()
    {
        parent::__construct(__('device-auth::messages.challenge_expired'));
    }

    public function reason(): string
    {
        return 'expired';
    }
}
