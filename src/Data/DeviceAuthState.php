<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use AbdulWahhabKhan\DeviceAuth\Data\Concerns\SerializesToArray;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Device-auth state for the current request, shared with every page
 * (Inertia prop "deviceAuth", Livewire/Blade variable $deviceAuth).
 */
final readonly class DeviceAuthState implements PageData
{
    use SerializesToArray;

    public function __construct(
        public bool $enabled,
        public ?Device $device,
        public bool $confirmRequired,
        public ?string $flash,
        public bool $isAdmin,
        public ?int $pendingCount,
        public ?string $confirmStatusUrl,
        public ?string $confirmOptionsUrl,
        public ?string $confirmUrl,
        public ?string $adminUrl,
    ) {}

    /** Null when nobody is signed in. */
    public static function fromRequest(Request $request): ?self
    {
        $user = $request->user();

        if ($user === null || ! $request->hasSession()) {
            return null;
        }

        $device = DeviceAuth::currentDevice($request);
        $isAdmin = DeviceAuth::isAdmin($user);
        $url = static fn (string $name): ?string => Route::has($name) ? route($name) : null;
        $flash = $request->session()->get(DeviceAuth::SESSION_FLASH);

        return new self(
            enabled: DeviceAuth::enabled(),
            device: $device,
            confirmRequired: (bool) $request->session()->get(DeviceAuth::SESSION_CONFIRM_REQUIRED, false),
            flash: is_string($flash) ? $flash : null,
            isAdmin: $isAdmin,
            pendingCount: $isAdmin && $device instanceof Device
                ? Device::query()->withStatus(DeviceStatus::Pending)->count()
                : null,
            confirmStatusUrl: $url('device-auth.confirm.status'),
            confirmOptionsUrl: $url('device-auth.confirm.options'),
            confirmUrl: $url('device-auth.confirm.store'),
            adminUrl: $isAdmin ? $url('device-auth.admin.index') : null,
        );
    }

    /**
     * @return array{
     *     enabled: bool,
     *     device: array{id: int, name: string, reference: string}|null,
     *     confirmRequired: bool,
     *     flash: string|null,
     *     isAdmin: bool,
     *     pendingCount: int|null,
     *     urls: array{confirmStatus: string|null, confirmOptions: string|null, confirm: string|null, admin: string|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'device' => $this->device instanceof Device ? [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'reference' => $this->device->reference(),
            ] : null,
            'confirmRequired' => $this->confirmRequired,
            'flash' => $this->flash,
            'isAdmin' => $this->isAdmin,
            'pendingCount' => $this->pendingCount,
            'urls' => [
                'confirmStatus' => $this->confirmStatusUrl,
                'confirmOptions' => $this->confirmOptionsUrl,
                'confirm' => $this->confirmUrl,
                'admin' => $this->adminUrl,
            ],
        ];
    }
}
