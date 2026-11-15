<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int|null $tenant_id
 * @property string $name
 * @property string|null $code
 * @property string $status
 * @property bool $all_devices
 * @property string|null $merged_into_id
 * @property-read int|null $devices_count
 * @property-read int|null $templates_count
 * @property-read int|null $attendance_events_count
 * @property-read int|null $face_templates_count
 * @property-read int|null $fp_templates_count
 */
#[Fillable(['name', 'code', 'status', 'all_devices'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $attributes = ['status' => self::STATUS_ACTIVE, 'all_devices' => false];

    protected function casts(): array
    {
        return ['all_devices' => 'boolean'];
    }

    /** @return BelongsToMany<Device, $this> */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'employee_device');
    }

    /** @return HasMany<BiometricTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(BiometricTemplate::class);
    }

    /** @return HasMany<AttendanceEvent, $this> */
    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    public function isAllowedOn(Device $device): bool
    {
        return $this->all_devices || $this->devices()->whereKey($device->id)->exists();
    }
}
