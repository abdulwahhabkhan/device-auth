<?php

namespace AbdulWahhabKhan\DeviceAuth\Events;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An administrator rejected a pending request.
 */
class DeviceRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly mixed $actor = null,
    ) {}
}
