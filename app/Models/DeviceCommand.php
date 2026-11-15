<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $device_id
 * @property string $name
 * @property array<string, mixed>|null $args
 * @property string $status
 * @property Carbon $issued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $completed_at
 * @property string|null $result_detail
 * @property Carbon $expires_at
 */
class DeviceCommand extends Model
{
    use HasUuids;

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    /** Command name => validation rules for its args. Add new commands here. */
    public const COMMANDS = [
        'sync_now' => [],
        'reboot' => [],
        'show_message' => [
            'text' => ['required', 'string', 'max:120'],
            'seconds' => ['required', 'integer', 'min:1', 'max:600'],
        ],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::QUEUED, self::SENT], true);
    }

    /** @return array{id: string, name: string, args: object, issued_at: string} */
    public function toDevicePayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'args' => (object) ($this->args ?? []),
            'issued_at' => $this->issued_at->toIso8601ZuluString(),
        ];
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withoutGlobalScopes();
    }
}
