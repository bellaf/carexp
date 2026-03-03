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
        $volume = $this->faker->randomFloat(3, 5, 22);

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'log_date' => $this->faker->date(),
            'odometer' => $this->faker->numberBetween(0, 220000),
            'volume' => $volume,
            'volume_unit' => $this->faker->randomElement(['gallons', 'litres']),
            'price_per_unit' => $this->faker->randomFloat(3, 1, 8),
            'full_tank' => $this->faker->boolean(90),
            'calculated_efficiency' => $this->faker->optional()->randomFloat(3, 5, 55),
        ];
    }
}
