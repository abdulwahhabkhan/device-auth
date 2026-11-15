<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Re-confirm the current device before a sensitive action
 * (the device-key version of Laravel's password confirmation).
 */
interface ConfirmsDevice
{
    /**
     * @param  array<string, mixed>  $input  the browser's assertion
     *
     * @throws ValidationException
     * @throws DeviceAuthException
     */
    public function confirm(Request $request, array $input): Device;
}
