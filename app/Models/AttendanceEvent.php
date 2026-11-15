<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_id
 * @property string|null $employee_id
 * @property int $device_event_id
 * @property Carbon|null $occurred_at
 * @property int|null $device_uptime_s
 * @property string $method
 * @property float|null $similarity
 * @property array<string, mixed> $raw
 * @property Carbon $received_at
 */
class AttendanceEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'raw' => 'array',
            'similarity' => 'float',
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
