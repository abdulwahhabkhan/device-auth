<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Approve from the terminal: needed for the very first device, since the
 * admin screen itself is only reachable from an approved device.
 */
#[AsCommand(name: 'device-auth:approve')]
class ApproveDeviceCommand extends Command
{
    protected $signature = 'device-auth:approve {device : Device ID or reference (DEV-00001)} {--name= : Rename the device}';

    protected $description = 'Approve a device';

    public function handle(ApprovesDevice $action): int
    {
        $device = Device::findByReference($this->deviceArgument());

        if (! $device instanceof Device) {
            $this->components->error('Device not found. Run device-auth:list to see IDs.');

            return self::FAILURE;
        }

        $name = $this->option('name');

        try {
            $action->approve($device, null, [
                'name' => is_string($name) && $name !== '' ? $name : $device->name,
                'review_note' => __('device-auth::messages.approved_from_cli'),
            ]);
        } catch (DeviceAuthException|ValidationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('%s "%s" approved.', $device->reference(), $device->name));

        return self::SUCCESS;
    }

    private function deviceArgument(): string
    {
        $device = $this->argument('device');

        return is_string($device) ? $device : '';
    }
}
