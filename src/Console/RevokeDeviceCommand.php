<?php

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRevoked;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;

/**
 * Emergency revoke, e.g. for a lost laptop when no admin device is at hand.
 */
class RevokeDeviceCommand extends Command
{
    protected $signature = 'device-auth:revoke {device : Device ID or reference (DEV-00001)} {--reason= : Shown to the user}';

    protected $description = 'Revoke a device';

    public function handle(): int
    {
        $device = Device::query()->find((int) preg_replace('/\D/', '', (string) $this->argument('device')));

        if (! $device) {
            $this->components->error('Device not found. Run device-auth:list to see IDs.');

            return self::FAILURE;
        }

        $device->forceFill([
            'status' => DeviceStatus::Revoked,
            'reviewed_at' => now(),
            'review_note' => $this->option('reason') ?: 'Revoked from the command line.',
        ])->save();

        DeviceRevoked::dispatch($device, null);

        $this->components->info("{$device->reference()} \"{$device->name}\" revoked.");

        return self::SUCCESS;
    }
}
