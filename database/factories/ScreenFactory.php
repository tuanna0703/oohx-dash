<?php

namespace Database\Factories;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScreenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id'    => Owner::factory(),
            'site_id'     => Site::factory(),
            'external_id' => $this->faker->unique()->bothify('SCR-####??'),
            'name'        => $this->faker->company() . ' Screen',
            'active'      => true,
            'status'      => 'online',
            'player_type' => 'adtrue_android',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
