<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Models;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An approved (or pending) device key: the equivalent of a line in authorized_keys.
 *
 * @phpstan-import-type UserSummary from DeviceAuth
 *
 * @phpstan-type PublicSummary array{reference: string, name: string, status: value-of<DeviceStatus>, requested_at: string|null, review_note: string|null}
 * @phpstan-type AdminSummary array{id: int, reference: string, name: string, status: value-of<DeviceStatus>, device_type: string|null, os: string|null, browser: string|null, request_ip: string|null, requester: UserSummary|null, created_at: string|null, last_seen_at: string|null, backup_eligible: bool}
 * @phpstan-type AdminDetails array{id: int, reference: string, name: string, status: value-of<DeviceStatus>, device_type: string|null, os: string|null, browser: string|null, request_ip: string|null, requester: UserSummary|null, created_at: string|null, last_seen_at: string|null, backup_eligible: bool, request_note: string|null, user_agent: string|null, os_family: string|null, device_model: string|null, client_meta: array<string, mixed>, aaguid: string|null, backed_up: bool, reviewer: UserSummary|null, reviewed_at: string|null, review_note: string|null, last_ip: string|null}
 * @phpstan-type ShownDevice array{id: int, reference: string, name: string, status: value-of<DeviceStatus>, device_type: string|null, os: string|null, browser: string|null, request_ip: string|null, requester: UserSummary|null, created_at: string|null, last_seen_at: string|null, backup_eligible: bool, request_note: string|null, user_agent: string|null, os_family: string|null, device_model: string|null, client_meta: array<string, mixed>, aaguid: string|null, backed_up: bool, reviewer: UserSummary|null, reviewed_at: string|null, review_note: string|null, last_ip: string|null, authenticator: string|null, is_current: bool}
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
 * @property int|string|null $requested_by
 * @property string|null $request_note
 * @property string|null $request_ip
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $os_family
 * @property string|null $device_type
 * @property string|null $device_model
 * @property array<string, mixed>|null $client_meta
 * @property int|string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property string|null $last_ip
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $requester
 * @property-read Model|null $reviewer
 */
class Device extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['public_key', 'credential_id'];

    public function getTable(): string
    {
        return DeviceAuth::table('devices');
    }

    /** @return array<string, string> */
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

    /** @return BelongsTo<Model, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'requested_by');
    }

    /** @return BelongsTo<Model, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(DeviceAuth::userModel(), 'reviewed_by');
    }

    /** @return HasMany<DeviceLogin, $this> */
    public function logins(): HasMany
    {
        return $this->hasMany(DeviceLogin::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeWithStatus(Builder $query, DeviceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Number of devices per status, every status present.
     *
     * @return array<value-of<DeviceStatus>, int>
     */
    public static function countsByStatus(): array
    {
        /** @var array<string, int|string> $totals */
        $totals = static::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $counts = [];

        foreach (DeviceStatus::cases() as $status) {
            $counts[$status->value] = (int) ($totals[$status->value] ?? 0);
        }

        return $counts;
    }

    /** Find by numeric id or reference ("DEV-00042"). */
    public static function findByReference(string $reference): ?self
    {
        $id = (int) preg_replace('/\D/', '', $reference);

        return $id > 0 ? static::query()->find($id) : null;
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::Active;
    }

    public function reference(): string
    {
        return 'DEV-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function describe(): string
    {
        return collect([$this->device_type, $this->os, $this->browser])->filter()->implode(', ');
    }

    /**
     * What the person at the device may see about their own request.
     *
     * @return PublicSummary
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

    /** @return AdminSummary */
    public function adminSummary(): array
    {
        return [
            'id' => $this->id,
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

    /** @return AdminDetails */
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
