<?php

namespace App\Models\Concerns;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes every query to the current tenant (NULL tenant included) and
 * stamps tenant_id on create.
 *
 * @property int|null $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app(CurrentTenant::class)->id();
            $column = $query->qualifyColumn('tenant_id');

            $tenantId === null
                ? $query->whereNull($column)
                : $query->where($column, $tenantId);
        });

        static::creating(function ($model) {
            if (! array_key_exists('tenant_id', $model->getAttributes())) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }
}
