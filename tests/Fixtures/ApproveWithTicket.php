<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Fixtures;

use AbdulWahhabKhan\DeviceAuth\Actions\ApproveDevice;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Validator;

/** An app's own approval rule: a ticket number is required. */
final class ApproveWithTicket extends ApproveDevice
{
    public function approve(Device $device, ?Authenticatable $reviewer, array $input, ?Device $current = null): Device
    {
        Validator::make($input, ['ticket' => ['required', 'string']])->validate();

        return parent::approve($device, $reviewer, $input, $current);
    }
}
