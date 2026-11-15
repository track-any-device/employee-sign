<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return ['site_id' => Site::factory(), 'name' => 'Main gate'];
    }
}
