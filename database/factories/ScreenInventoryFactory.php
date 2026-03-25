<?php

namespace Database\Factories;

use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScreenInventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'screen_id'           => Screen::factory(),
            'venue_type'          => $this->faker->randomElement(['mall', 'outdoor', 'fnb', 'transit', 'office']),
            'spot_length'         => 15,
            'loop_length'         => 120,
            'weekly_impressions'  => 70000,
            'floor_cpm'           => $this->faker->numberBetween(50000, 500000),
            'floor_cpm_currency'  => 'VND',
            'floor_cpm_usd'       => 2.00,
            'programmatic_enabled'=> true,
            'timezone'            => 'Asia/Ho_Chi_Minh',
        ];
    }

    public function venueType(string $type): static
    {
        return $this->state(['venue_type' => $type]);
    }
}
