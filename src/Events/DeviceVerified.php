<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Events;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device proved it holds its key (purpose: verify or confirm).
 */
class DeviceVerified
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly ?Authenticatable $user,
        public readonly string $purpose,
    ) {}
}
