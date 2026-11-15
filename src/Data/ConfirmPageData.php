<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use AbdulWahhabKhan\DeviceAuth\Data\Concerns\SerializesToArray;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;

/**
 * The full-page "confirm this device" screen.
 */
final readonly class ConfirmPageData implements PageData
{
    use SerializesToArray;

    public function __construct(
        public ?Device $device,
        public string $optionsUrl,
        public string $confirmUrl,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            device: DeviceAuth::currentDevice($request),
            optionsUrl: route('device-auth.confirm.options'),
            confirmUrl: route('device-auth.confirm.store'),
        );
    }

    /**
     * @return array{
     *     device: array{name: string, reference: string}|null,
     *     urls: array{options: string, confirm: string}
     * }
     */
    public function toArray(): array
    {
        return [
            'device' => $this->device instanceof Device
                ? ['name' => $this->device->name, 'reference' => $this->device->reference()]
                : null,
            'urls' => [
                'options' => $this->optionsUrl,
                'confirm' => $this->confirmUrl,
            ],
        ];
    }
}
