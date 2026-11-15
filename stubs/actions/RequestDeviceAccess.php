<?php

declare(strict_types=1);

namespace App\Actions\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Contracts\RequestsDeviceAccess;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceAccessRequested;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\NewDeviceRequest;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * Stores the device's new public key as pending and tells the administrators.
 */
class RequestDeviceAccess implements RequestsDeviceAccess
{
    public function __construct(
        private readonly DeviceKeyService $keys,
        private readonly DeviceMetadata $metadata,
    ) {}

    public function request(Request $request, array $input): Device
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'credential' => ['required', 'array'],
            'credential.clientDataJSON' => ['required', 'string', 'max:8192'],
            'credential.attestationObject' => ['required', 'string', 'max:32768'],
            'client' => ['nullable', 'array'],
        ];

        foreach (DeviceMetadata::CLIENT_KEYS as $key) {
            $rules['client.'.$key] = ['nullable', 'max:100'];
        }

        /** @var array{name: string, note?: string|null, credential: array{clientDataJSON: string, attestationObject: string}, client?: array<string, mixed>|null} $data */
        $data = Validator::make($input, $rules)->validate();

        $key = $this->keys->finishRegistration($request, $data['credential']);
        $user = $request->user();

        $device = Device::query()->create(
            $key->toAttributes()
            + $this->metadata->describe($request, $data['client'] ?? [])
            + [
                'name' => trim($data['name']),
                'request_note' => $data['note'] ?? null,
                'requested_by' => $user?->getAuthIdentifier(),
                'status' => DeviceStatus::Pending,
            ]
        );

        event(new DeviceAccessRequested($device, $user));

        if (DeviceAuth::notificationChannels() !== []) {
            Notification::send(DeviceAuth::admins(), new NewDeviceRequest($device));
        }

        return $device;
    }
}
