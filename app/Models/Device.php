<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $tenant_id
 * @property string $name
 * @property int|null $site_id
 * @property int|null $location_id
 * @property string|null $hardware_id
 * @property string|null $mac
 * @property string|null $model
 * @property string|null $firmware_version
 * @property string|null $variant
 * @property string $status
 * @property Carbon|null $last_seen_at
 * @property string|null $last_ip
 * @property bool $online
 * @property array<string, mixed>|null $last_stats
 * @property Carbon|null $claimed_at
 */
#[Fillable(['name', 'site_id', 'location_id'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $attributes = ['status' => self::STATUS_PENDING, 'online' => false];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'claimed_at' => 'datetime',
            'online' => 'boolean',
            'last_stats' => 'array',
        ];
    }

    /** "private-device.{id}" without the "private-" prefix Laravel adds. */
    public function channelName(): string
    {
        return 'device.'.$this->id;
    }

    /** Last 4 hex digits of the hardware id, as shown to users ("09A4"). */
    public function shortHardwareId(): ?string
    {
        return $this->hardware_id ? strtoupper(substr($this->hardware_id, -4)) : null;
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<DeviceToken, $this> */
    public function tokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** @return HasMany<DevicePairingToken, $this> */
    public function pairingTokens(): HasMany
    {
        return $this->hasMany(DevicePairingToken::class);
    }

    /** @return HasMany<DeviceCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /** @return HasMany<DeviceConnectionLog, $this> */
    public function connectionLogs(): HasMany
    {
        return $this->hasMany(DeviceConnectionLog::class);
    }

    /** @return HasMany<AttendanceEvent, $this> */
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    /**
     * Employees explicitly assigned (employees with all_devices are not listed here).
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_device');
    }

    /** @param  array<string, mixed>  $meta */
    public function log(string $event, ?string $ip = null, array $meta = []): void
    {
        $this->connectionLogs()->create(['event' => $event, 'ip' => $ip, 'meta' => $meta ?: null]);
    }
}
