<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Props for one device-auth page. Every frontend (Inertia, Livewire, a
 * SPA reading the JSON) receives exactly this data.
 *
 * @extends Arrayable<string, mixed>
 */
interface PageData extends Arrayable, JsonSerializable
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
