<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceDate = fake()->dateTimeBetween('-3 years', 'now');

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'service_type' => fake()->randomElement(['oil_change', 'tire_rotation', 'brake_service']),
            'provider' => fake()->optional()->company(),
            'service_date' => $serviceDate->format('Y-m-d'),
            'odometer' => fake()->optional()->numberBetween(0, 220000),
            'notes' => fake()->optional()->sentence(),
            'next_due_date' => fake()->optional()->dateTimeBetween($serviceDate, '+2 years')?->format('Y-m-d'),
            'next_due_odometer' => fake()->optional()->numberBetween(1000, 230000),
        ];
    }
}
