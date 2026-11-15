<?php

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

class ChallengeExpired extends DeviceAuthException
{
    public function __construct()
    {
        parent::__construct('The request expired. Try again.');
    }

    public function reason(): string
    {
        return 'expired';
    }
}
