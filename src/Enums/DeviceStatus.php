<?php

namespace AbdulWahhabKhan\DeviceAuth\Enums;

enum DeviceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Active => 'Approved',
            self::Rejected => 'Rejected',
            self::Revoked => 'Revoked',
        };
    }
}
