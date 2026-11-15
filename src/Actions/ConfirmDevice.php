<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\ConfirmsDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceVerified;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceNotApproved;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Re-confirm the current device before a sensitive action.
 */
class ConfirmDevice implements ConfirmsDevice
{
    public function __construct(private readonly DeviceKeyService $keys) {}

    public function confirm(Request $request, array $input): Device
    {
        /** @var array{id: string, clientDataJSON: string, authenticatorData: string, signature: string} $assertion */
        $assertion = Validator::make($input, [
            'id' => ['required', 'string', 'max:1024'],
            'clientDataJSON' => ['required', 'string', 'max:8192'],
            'authenticatorData' => ['required', 'string', 'max:8192'],
            'signature' => ['required', 'string', 'max:2048'],
        ])->validate();

        // Only the device this session signed in with may confirm.
        $device = $this->keys->finishVerification(
            $request,
            CeremonyPurpose::Confirm,
            $assertion,
            DeviceAuth::currentDevice($request),
        );

        if (! $device->isActive()) {
            throw new DeviceNotApproved($device);
        }

        DeviceAuth::markConfirmed($request);

        $user = $request->user();

        if ($user instanceof Authenticatable) {
            DeviceLogin::record($device, $user, $request, CeremonyPurpose::Confirm);
        }

        event(new DeviceVerified($device, $user, CeremonyPurpose::Confirm->value));

        return $device;
    }
}
