<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Step 2 after the password: the device signs the challenge with an approved key.
 */
interface VerifiesDevice
{
    /**
     * @param  array<string, mixed>  $input  the browser's assertion
     *
     * @throws ValidationException
     * @throws DeviceAuthException
     */
    public function verify(Request $request, array $input): Device;
}
