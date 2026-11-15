<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use AbdulWahhabKhan\DeviceAuth\Data\Concerns\SerializesToArray;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Services\AaguidDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * One device in the admin area: details, review form and recent sign-ins.
 *
 * @phpstan-import-type ShownDevice from Device
 * @phpstan-import-type UserSummary from DeviceAuth
 *
 * @phpstan-type LoginEntry array{id: int, user: UserSummary|null, ip: string|null, purpose: string, created_at: string|null}
 */
final readonly class DeviceShowPageData implements PageData
{
    use SerializesToArray;

    /**
     * @param  Collection<int, DeviceLogin>  $logins
     */
    public function __construct(
        public Device $device,
        public ?string $authenticator,
        public bool $isCurrent,
        public Collection $logins,
    ) {}

    public static function fromRequest(Request $request, Device $device): self
    {
        $device->loadMissing('requester', 'reviewer');

        return new self(
            device: $device,
            authenticator: resolve(AaguidDirectory::class)->name($device->aaguid),
            isCurrent: DeviceAuth::currentDevice($request)?->is($device) ?? false,
            logins: $device->logins()->with('user')->latest('created_at')->limit(10)->get(),
        );
    }

    /**
     * @return array{
     *     device: ShownDevice,
     *     logins: list<LoginEntry>,
     *     urls: array{index: string, approve: string, reject: string, revoke: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'device' => $this->device->adminDetails() + [
                'authenticator' => $this->authenticator,
                'is_current' => $this->isCurrent,
            ],
            'logins' => array_values($this->logins
                ->map(static fn (DeviceLogin $login): array => [
                    'id' => $login->id,
                    'user' => DeviceAuth::describeUser($login->user),
                    'ip' => $login->ip,
                    'purpose' => $login->purpose,
                    'created_at' => $login->created_at?->toIso8601String(),
                ])
                ->all()),
            'urls' => [
                'index' => route('device-auth.admin.index', ['status' => $this->device->status->value]),
                'approve' => route('device-auth.admin.approve', $this->device),
                'reject' => route('device-auth.admin.reject', $this->device),
                'revoke' => route('device-auth.admin.revoke', $this->device),
            ],
        ];
    }
}
