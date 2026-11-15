<?php

namespace AbdulWahhabKhan\DeviceAuth\Models;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approved (or pending) device key — the equivalent of a line in authorized_keys.
 *
 * @property int $id
 * @property string $name
 * @property DeviceStatus $status
 * @property string $credential_id
 * @property string $public_key
 * @property int $sign_count
 * @property string|null $aaguid
 * @property bool $backup_eligible
 * @property bool $backed_up
 * @property mixed $requested_by
 * @property string|null $request_note
 * @property string|null $request_ip
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $os_family
 * @property string|null $device_type
 * @property string|null $device_model
 * @property array<string, mixed>|null $client_meta
 * @property mixed $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property string|null $last_ip
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Device extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['public_key', 'credential_id'];

    public function getTable(): string
    {
        return config('device-auth.tables.devices', 'device_auth_devices');
    }

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'client_meta' => 'array',
            'backup_eligible' => 'boolean',
            'backed_up' => 'boolean',
            'sign_count' => 'integer',
            'reviewed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'reviewed_by');
    }

    public function logins(): HasMany
    {
        return $this->hasMany(DeviceLogin::class);
    }

    public function scopeWithStatus(Builder $query, DeviceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::Active;
    }

    public function reference(): string
    {
        return 'DEV-'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    /**
     * What the person at the device may see about their own request.
     *
     * @return array<string, mixed>
     */
    public function publicSummary(): array
    {
        return [
            'reference' => $this->reference(),
            'name' => $this->name,
            'status' => $this->status->value,
            'requested_at' => $this->created_at?->toIso8601String(),
            'review_note' => in_array($this->status, [DeviceStatus::Rejected, DeviceStatus::Revoked], true)
                ? $this->review_note
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function adminSummary(): array
    {
        return [
            'id' => $this->getKey(),
            'reference' => $this->reference(),
            'name' => $this->name,
            'status' => $this->status->value,
            'device_type' => $this->device_type,
            'os' => $this->os,
            'browser' => $this->browser,
            'request_ip' => $this->request_ip,
            'requester' => DeviceAuth::describeUser($this->requester),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'backup_eligible' => $this->backup_eligible,
        ];
    }

    /** @return array<string, mixed> */
    public function adminDetails(): array
    {
        return $this->adminSummary() + [
            'request_note' => $this->request_note,
            'user_agent' => $this->user_agent,
            'os_family' => $this->os_family,
            'device_model' => $this->device_model,
            'client_meta' => $this->client_meta ?? [],
            'aaguid' => $this->aaguid,
            'backed_up' => $this->backed_up,
            'reviewer' => DeviceAuth::describeUser($this->reviewer),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'last_ip' => $this->last_ip,
        ];
    }
}
