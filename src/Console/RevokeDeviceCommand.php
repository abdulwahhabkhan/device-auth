<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Emergency revoke, e.g. for a lost laptop when no admin device is at hand.
 */
#[AsCommand(name: 'device-auth:revoke')]
class RevokeDeviceCommand extends Command
{
    protected $signature = 'device-auth:revoke {device : Device ID or reference (DEV-00001)} {--reason= : Shown to the user}';

    protected $description = 'Revoke a device';

    public function handle(RevokesDevice $action): int
    {
        $device = Device::findByReference($this->deviceArgument());

        if (! $device instanceof Device) {
            $this->components->error('Device not found. Run device-auth:list to see IDs.');

            return self::FAILURE;
        }

        $reason = $this->option('reason');

        try {
            $action->revoke($device, null, [
                'review_note' => is_string($reason) && $reason !== '' ? $reason : __('device-auth::messages.revoked_from_cli'),
            ]);
        } catch (DeviceAuthException|ValidationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%s "%s" revoked.', $device->reference(), $device->name));

        return self::SUCCESS;
    }

    private function deviceArgument(): string
    {
        $device = $this->argument('device');

        return is_string($device) ? $device : '';
    }
}
