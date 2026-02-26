<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FuelLog>
 */
class FuelLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $volume = fake()->randomFloat(3, 5, 22);

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'log_date' => fake()->date(),
            'odometer' => fake()->numberBetween(0, 220000),
            'volume' => $volume,
            'volume_unit' => fake()->randomElement(['gallons', 'liters']),
            'price_per_unit' => fake()->randomFloat(3, 1, 8),
            'full_tank' => fake()->boolean(90),
            'calculated_efficiency' => fake()->optional()->randomFloat(3, 5, 55),
        ];
    }
}
