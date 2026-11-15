<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy extends TenantResourcePolicy
{
    public function view(User $user, Site $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function update(User $user, Site $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function delete(User $user, Site $model): bool
    {
        return $this->allowed($user, $model);
    }
}
