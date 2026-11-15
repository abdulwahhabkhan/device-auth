<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Request access for this device", step 1: WebAuthn creation options.
 */
interface BeginsDeviceRegistration
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> PublicKeyCredentialCreationOptions for the browser
     *
     * @throws ValidationException
     * @throws DeviceAuthException
     */
    public function begin(Request $request, array $input): array;
}
