<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use Illuminate\Http\Request;

class BeginDeviceVerification implements BeginsDeviceVerification
{
    public function __construct(private readonly DeviceKeyService $keys) {}

    public function begin(Request $request, CeremonyPurpose $purpose): array
    {
        // Confirmation must come from the device this session signed in with.
        $only = $purpose === CeremonyPurpose::Confirm ? DeviceAuth::currentDevice($request) : null;

        return $this->keys->beginVerification($request, $purpose, $only);
    }
}
