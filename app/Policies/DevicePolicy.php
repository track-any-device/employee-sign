<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy extends TenantResourcePolicy
{
    public function view(User $user, Device $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function update(User $user, Device $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function delete(User $user, Device $model): bool
    {
        return $this->allowed($user, $model);
    }
}
