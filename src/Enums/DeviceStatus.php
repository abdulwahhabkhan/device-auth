<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Enums;

enum DeviceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    public function label(): string
    {
        return __('device-auth::messages.status.'.$this->value);
    }
}
