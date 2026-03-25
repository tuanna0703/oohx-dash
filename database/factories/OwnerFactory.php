<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OwnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => $this->faker->company(),
            'slug'           => $this->faker->unique()->slug(),
            'type'           => 'media_owner',
            'onboard_method' => 'api',
            'revenue_share_pct' => 70.00,
            'status'         => 'active',
        ];
    }
}
