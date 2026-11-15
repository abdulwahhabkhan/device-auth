<?php

namespace AbdulWahhabKhan\DeviceAuth\Events;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An administrator approved a device.
 */
class DeviceApproved
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly mixed $actor = null,
    ) {}
}
