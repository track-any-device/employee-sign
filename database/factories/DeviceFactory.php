<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Device> */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Gate '.fake()->unique()->numberBetween(1, 9999)];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Device::STATUS_ACTIVE,
            'hardware_id' => strtoupper(fake()->regexify('[0-9A-F]{12}')),
            'model' => 'TAD-PAK AD',
            'firmware_version' => '0.2.1',
            'variant' => 'AD-DEV-1',
            'claimed_at' => now(),
        ]);
    }

    public function forTenant(?int $tenantId): static
    {
        return $this->state(fn () => ['tenant_id' => $tenantId]);
    }
}
