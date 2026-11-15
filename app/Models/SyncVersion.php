<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $employee_id
 * @property string $op
 * @property string|null $merged_into
 */
class SyncVersion extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
