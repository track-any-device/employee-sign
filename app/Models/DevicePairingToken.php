<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $device_id
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $claimed_at
 * @property int|null $created_by
 */
class DevicePairingToken extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'claimed_at' => 'datetime'];
    }

    /** @param Builder<self> $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('claimed_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withoutGlobalScopes();
    }
}
