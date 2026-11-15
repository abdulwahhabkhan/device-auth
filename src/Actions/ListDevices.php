<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Devices of one status, newest first. Search matches the name, IP
 * addresses or the reference number (DEV-00042).
 */
class ListDevices implements ListsDevices
{
    public function list(DeviceStatus $status, string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        $search = trim($search);

        return Device::query()
            ->with('requester')
            ->withStatus($status)
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $id = (int) preg_replace('/\D/', '', $search);

                $query->where(static function (Builder $q) use ($search, $id): void {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('request_ip', 'like', '%'.$search.'%')
                        ->orWhere('last_ip', 'like', '%'.$search.'%');

                    if ($id > 0) {
                        $q->orWhere('id', $id);
                    }
                });
            })
            ->latest($status === DeviceStatus::Pending ? 'created_at' : 'updated_at')
            ->paginate($perPage);
    }
}
