<?php

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceApproved;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;

/**
 * Approve from the terminal — needed for the very first device, since the
 * admin screen itself is only reachable from an approved device.
 */
class ApproveDeviceCommand extends Command
{
    protected $signature = 'device-auth:approve {device : Device ID or reference (DEV-00001)} {--name= : Rename the device}';

    protected $description = 'Approve a device';

    public function handle(): int
    {
        $device = Device::query()->find((int) preg_replace('/\D/', '', (string) $this->argument('device')));

        if (! $device) {
            $this->components->error('Device not found. Run device-auth:list to see IDs.');

            return self::FAILURE;
        }

        $device->forceFill(array_filter([
            'name' => $this->option('name'),
            'status' => DeviceStatus::Active,
            'reviewed_at' => now(),
            'review_note' => 'Approved from the command line.',
        ]))->save();

        DeviceApproved::dispatch($device, null);

        $this->components->info("{$device->reference()} \"{$device->name}\" approved.");

        return self::SUCCESS;
    }
}
