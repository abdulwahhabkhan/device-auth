<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use Illuminate\Http\Request;

/**
 * WebAuthn request options for verifying (after login) or confirming
 * (before a sensitive action) the current device.
 */
interface BeginsDeviceVerification
{
    /**
     * @return array<string, mixed> PublicKeyCredentialRequestOptions for the browser
     */
    public function begin(Request $request, CeremonyPurpose $purpose): array;
}
