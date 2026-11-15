<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => 'E'.fake()->unique()->numerify('#####'),
            'status' => Employee::STATUS_ACTIVE,
            'all_devices' => false,
        ];
    }

    public function forTenant(?int $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }
}
