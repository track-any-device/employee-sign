<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BiometricConsent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['given_at' => 'datetime', 'employee_ids' => 'array'];
    }
}
