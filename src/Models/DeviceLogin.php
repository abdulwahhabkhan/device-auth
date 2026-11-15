<?php

namespace AbdulWahhabKhan\DeviceAuth\Models;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail: which user used which device, and when.
 *
 * @property int $id
 * @property int $device_id
 * @property mixed $user_id
 * @property string|null $ip
 * @property string $purpose
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class DeviceLogin extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('device-auth.tables.logins', 'device_auth_logins');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'user_id');
    }
}
