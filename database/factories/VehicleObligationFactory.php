<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleObligation>
 */
class VehicleObligationFactory extends Factory
{
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $dueDate = (clone $startDate)->modify('+1 year');

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'ledger_entry_id' => null,
            'renewed_from_id' => null,
            'obligation_type' => $this->faker->randomElement(['insurance', 'tax', 'mot']),
            'provider' => $this->faker->optional()->company(),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'start_date' => $startDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'amount' => $this->faker->optional()->randomFloat(2, 40, 1200),
            'notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'completed_at' => null,
        ];
    }
}
