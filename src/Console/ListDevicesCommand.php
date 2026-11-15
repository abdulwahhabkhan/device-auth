<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Console;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'device-auth:list')]
class ListDevicesCommand extends Command
{
    protected $signature = 'device-auth:list {--status= : pending, active, rejected or revoked}';

    protected $description = 'List registered devices';

    public function handle(): int
    {
        $option = $this->option('status');
        $status = is_string($option) && $option !== '' ? DeviceStatus::tryFrom($option) : null;

        if (is_string($option) && $option !== '' && ! $status instanceof DeviceStatus) {
            $this->components->error('Unknown status. Use pending, active, rejected or revoked.');

            return self::FAILURE;
        }

        $devices = Device::query()
            ->with('requester')
            ->when($status, static fn (Builder $query, DeviceStatus $status): Builder => $query->where('status', $status->value))
            ->latest()
            ->get();

        if ($devices->isEmpty()) {
            $this->components->info('No devices found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Reference', 'Name', 'Status', 'Device', 'Requested by', 'IP', 'Requested'],
            $devices->map(static fn (Device $d): array => [
                $d->id,
                $d->reference(),
                $d->name,
                $d->status->value,
                $d->describe(),
                DeviceAuth::describeUser($d->requester)['email'] ?? DeviceAuth::describeUser($d->requester)['name'] ?? '-',
                $d->request_ip,
                $d->created_at?->diffForHumans(),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
