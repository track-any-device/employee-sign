<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Any verified user may manage the resources of the tenant they are acting
 * for. Today that is always the null tenant (Custom install); the global
 * tenant scope already hides other tenants' rows, this is the second check.
 */
abstract class TenantResourcePolicy
{
    protected function allowed(User $user, ?Model $model = null): bool
    {
        if (! $user->hasVerifiedEmail()) {
            return false;
        }

        return $model === null || $model->getAttribute('tenant_id') === app(CurrentTenant::class)->id();
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }
}
