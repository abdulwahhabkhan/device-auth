<?php

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;

class ListDevicesCommand extends Command
{
    protected $signature = 'device-auth:list {--status= : pending, active, rejected or revoked}';

    protected $description = 'List registered devices';

    public function handle(): int
    {
        $status = $this->option('status') ? DeviceStatus::tryFrom((string) $this->option('status')) : null;

        if ($this->option('status') && ! $status) {
            $this->components->error('Unknown status. Use pending, active, rejected or revoked.');

            return self::FAILURE;
        }

        $devices = Device::query()
            ->with('requester')
            ->when($status, fn ($q) => $q->withStatus($status))
            ->latest()
            ->get();

        if ($devices->isEmpty()) {
            $this->components->info('No devices found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Reference', 'Name', 'Status', 'Device', 'Requested by', 'IP', 'Requested'],
            $devices->map(fn (Device $d) => [
                $d->getKey(),
                $d->reference(),
                $d->name,
                $d->status->value,
                collect([$d->device_type, $d->os, $d->browser])->filter()->implode(', '),
                data_get($d->requester, 'email') ?? data_get($d->requester, 'name') ?? '-',
                $d->request_ip,
                $d->created_at?->diffForHumans(),
            ]),
        );

        return self::SUCCESS;
    }
}
