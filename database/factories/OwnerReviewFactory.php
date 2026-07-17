<?php

namespace Database\Factories;

use App\Models\OwnerReview;
use Illuminate\Database\Eloquent\Factories\Factory;

class OwnerReviewFactory extends Factory
{
    protected $model = OwnerReview::class;

    public function definition(): array
    {
        return [
            'rating'  => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->sentence(),
            'status'  => OwnerReview::STATUS_PENDING,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => OwnerReview::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
