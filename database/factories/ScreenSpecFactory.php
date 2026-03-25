<?php

namespace Database\Factories;

use App\Models\Screen;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScreenSpecFactory extends Factory
{
    public function definition(): array
    {
        $widthPx  = $this->faker->randomElement([1920, 1280, 1080, 1024]);
        $heightPx = $this->faker->randomElement([1080, 720, 1920, 768]);

        return [
            'screen_id'  => Screen::factory(),
            'width_px'   => $widthPx,
            'height_px'  => $heightPx,
            'width_cm'   => 150,
            'height_cm'  => 100,
            'width_unit' => 'cm',
            'height_unit'=> 'cm',
        ];
    }

    public function landscape(): static
    {
        return $this->state([
            'width_px'  => 1920,
            'height_px' => 1080,
            'width_cm'  => 200,
            'height_cm' => 112,
        ]);
    }

    public function portrait(): static
    {
        return $this->state([
            'width_px'  => 1080,
            'height_px' => 1920,
            'width_cm'  => 112,
            'height_cm' => 200,
        ]);
    }

    public function square(): static
    {
        return $this->state([
            'width_px'  => 1080,
            'height_px' => 1080,
            'width_cm'  => 100,
            'height_cm' => 100,
        ]);
    }

    public function billboard(): static
    {
        return $this->state([
            'width_px'  => 3840,
            'height_px' => 1080,
            'width_cm'  => 1400,
            'height_cm' => 600,
        ]);
    }
}
