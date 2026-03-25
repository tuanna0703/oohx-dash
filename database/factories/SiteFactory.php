<?php

namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id'    => Owner::factory(),
            'external_id' => $this->faker->unique()->bothify('SITE-####'),
            'name'        => $this->faker->company() . ' Site',
            'lat'         => $this->faker->latitude(10, 22),
            'lon'         => $this->faker->longitude(102, 110),
            'address'     => $this->faker->streetAddress(),
            'city'        => $this->faker->randomElement(['hanoi', 'hcm', 'danang', 'haiphong', 'cantho']),
            'region'      => $this->faker->city(),
            'country'     => 'VN',
            'status'      => 'active',
        ];
    }

    public function inCity(string $city): static
    {
        return $this->state(['city' => $city]);
    }
}
