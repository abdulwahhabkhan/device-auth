<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts;

use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The administrator's device list: one status at a time, optionally searched.
 */
interface ListsDevices
{
    /**
     * @return LengthAwarePaginator<int, Device>
     */
    public function list(DeviceStatus $status, string $search = '', int $perPage = 20): LengthAwarePaginator;
}
