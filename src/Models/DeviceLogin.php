<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Models;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Audit trail: which user used which device, and when.
 *
 * @property int $id
 * @property int $device_id
 * @property int|string $user_id
 * @property string|null $ip
 * @property string $purpose
 * @property Carbon|null $created_at
 * @property-read Device|null $device
 * @property-read Model|null $user
 */
class DeviceLogin extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return DeviceAuth::table('logins');
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'user_id');
    }

    public static function record(Device $device, Authenticatable $user, Request $request, CeremonyPurpose $purpose): self
    {
        return static::query()->create([
            'device_id' => $device->id,
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $request->ip(),
            'purpose' => $purpose->value,
        ]);
    }
}
