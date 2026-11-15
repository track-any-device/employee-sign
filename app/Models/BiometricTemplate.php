<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Template data is base64 of the binary template, encrypted at rest.
 * Never log it and never return it to web users.
 *
 * @property string $id
 * @property string $employee_id
 * @property string $type
 * @property string $model_version
 * @property string $data
 * @property float|null $quality
 * @property string|null $source_device_id
 * @property string $sha256
 */
class BiometricTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return ['data' => 'encrypted', 'quality' => 'float'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return array{id: string, type: string, model_version: string, data_base64: string, sha256: string} */
    public function toDevicePayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'model_version' => $this->model_version,
            'data_base64' => $this->data,
            'sha256' => $this->sha256,
        ];
    }
}
