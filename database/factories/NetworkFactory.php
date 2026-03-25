<?php
namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NetworkFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'owner_id' => Owner::factory(),
            'code'     => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'name'     => $name,
        ];
    }

    public function withCode(string $code, string $name): static
    {
        return $this->state(['code' => $code, 'name' => $name]);
    }
}
