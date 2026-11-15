<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\VerifiesDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceVerified;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceMismatch;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceNotApproved;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Step 2 after the password: the device proves it holds an approved key.
 */
class VerifyDevice implements VerifiesDevice
{
    public function __construct(
        private readonly DeviceKeyService $keys,
        private readonly DeviceMetadata $metadata,
    ) {}

    public function verify(Request $request, array $input): Device
    {
        /** @var array{id: string, clientDataJSON: string, authenticatorData: string, signature: string} $assertion */
        $assertion = Validator::make($input, [
            'id' => ['required', 'string', 'max:1024'],
            'clientDataJSON' => ['required', 'string', 'max:8192'],
            'authenticatorData' => ['required', 'string', 'max:8192'],
            'signature' => ['required', 'string', 'max:2048'],
        ])->validate();

        $device = $this->keys->finishVerification($request, CeremonyPurpose::Verify, $assertion);

        if (config('device-auth.enforce_platform_match', true) && ! $this->metadata->matchesDevice($request, $device)) {
            Log::warning('Device key used from a different kind of device.', [
                'device' => $device->id,
                'expected' => [$device->os_family, $device->device_type],
                'current' => $this->metadata->parse($request),
            ]);

            throw new DeviceMismatch(__('device-auth::messages.platform_mismatch'));
        }

        if (! $device->isActive()) {
            throw new DeviceNotApproved($device);
        }

        DeviceAuth::markVerified($request, $device);

        $user = $request->user();

        if ($user instanceof Authenticatable) {
            DeviceLogin::record($device, $user, $request, CeremonyPurpose::Verify);
        }

        event(new DeviceVerified($device, $user, CeremonyPurpose::Verify->value));

        return $device;
    }
}
