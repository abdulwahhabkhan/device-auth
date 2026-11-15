<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Request access for this device", step 2: store the new public key as pending.
 */
interface RequestsDeviceAccess
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws DeviceAuthException
     */
    public function request(Request $request, array $input): Device;
}
