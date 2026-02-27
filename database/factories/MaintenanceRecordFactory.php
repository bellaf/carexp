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
        $serviceDate = $this->faker->dateTimeBetween('-3 years', 'now');

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'service_type' => $this->faker->randomElement(['oil_change', 'tire_rotation', 'brake_service']),
            'provider' => $this->faker->optional()->company(),
            'service_date' => $serviceDate->format('Y-m-d'),
            'odometer' => $this->faker->optional()->numberBetween(0, 220000),
            'notes' => $this->faker->optional()->sentence(),
            'next_due_date' => $this->faker->optional()->dateTimeBetween($serviceDate, '+2 years')?->format('Y-m-d'),
            'next_due_odometer' => $this->faker->optional()->numberBetween(1000, 230000),
        ];
    }
}
