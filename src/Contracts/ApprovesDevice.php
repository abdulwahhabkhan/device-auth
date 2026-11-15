<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Exceptions\InvalidDeviceState;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

interface ApprovesDevice
{
    /**
     * @param  array<string, mixed>  $input
     * @param  Authenticatable|null  $reviewer  null when run from the command line
     * @param  Device|null  $current  the device the reviewer is using right now
     *
     * @throws ValidationException
     * @throws InvalidDeviceState
     */
    public function approve(Device $device, ?Authenticatable $reviewer, array $input, ?Device $current = null): Device;
}
