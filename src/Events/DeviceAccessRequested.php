<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Events;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device asked for access (a new pending key was stored).
 */
class DeviceAccessRequested
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly ?Authenticatable $actor = null,
    ) {}
}
