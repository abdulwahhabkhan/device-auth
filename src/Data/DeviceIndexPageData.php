<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Data\Concerns\SerializesToArray;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;

/**
 * The administrator's device list ("authorized_keys").
 *
 * @phpstan-import-type AdminSummary from Device
 *
 * @phpstan-type Page array{data: list<AdminSummary>, current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null, prev_page_url: string|null, next_page_url: string|null}
 */
final readonly class DeviceIndexPageData implements PageData
{
    use SerializesToArray;

    /**
     * @param  LengthAwarePaginator<int, Device>  $devices
     * @param  array<value-of<DeviceStatus>, int>  $counts
     */
    public function __construct(
        public DeviceStatus $status,
        public string $search,
        public array $counts,
        public LengthAwarePaginator $devices,
        public string $indexUrl,
    ) {}

    public static function fromRequest(Request $request, ListsDevices $lister): self
    {
        $status = DeviceStatus::tryFrom($request->string('status', DeviceStatus::Pending->value)->toString())
            ?? DeviceStatus::Pending;
        $search = $request->string('q')->trim()->toString();

        $devices = $lister->list($status, $search);

        if ($devices instanceof AbstractPaginator) {
            $devices->withQueryString();
        }

        return new self(
            status: $status,
            search: $search,
            counts: Device::countsByStatus(),
            devices: $devices,
            indexUrl: route('device-auth.admin.index'),
        );
    }

    /**
     * @return array{
     *     status: value-of<DeviceStatus>,
     *     search: string,
     *     counts: array<value-of<DeviceStatus>, int>,
     *     devices: Page,
     *     urls: array{index: string}
     * }
     */
    public function toArray(): array
    {
        $items = [];

        foreach ($this->devices->items() as $device) {
            $items[] = $device->adminSummary();
        }

        return [
            'status' => $this->status->value,
            'search' => $this->search,
            'counts' => $this->counts,
            'devices' => [
                'data' => $items,
                'current_page' => $this->devices->currentPage(),
                'last_page' => $this->devices->lastPage(),
                'per_page' => $this->devices->perPage(),
                'total' => $this->devices->total(),
                'from' => $this->devices->firstItem(),
                'to' => $this->devices->lastItem(),
                'prev_page_url' => $this->devices->previousPageUrl(),
                'next_page_url' => $this->devices->nextPageUrl(),
            ],
            'urls' => [
                'index' => $this->indexUrl,
            ],
        ];
    }
}
