<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy extends TenantResourcePolicy
{
    public function view(User $user, Employee $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function update(User $user, Employee $model): bool
    {
        return $this->allowed($user, $model);
    }

    public function delete(User $user, Employee $model): bool
    {
        return $this->allowed($user, $model);
    }
}
