<?php

namespace AbdulWahhabKhan\DeviceAuth\Events;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device proved it holds its key (purpose: verify or confirm).
 */
class DeviceVerified
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly mixed $user,
        public readonly string $purpose,
    ) {}
}
