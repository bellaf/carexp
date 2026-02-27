<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entryType = $this->faker->randomElement(['expense', 'income']);
        $cadence = $this->faker->randomElement(['monthly', 'quarterly', 'yearly']);

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'account_id' => Account::factory()->state([
                'group' => $entryType,
            ]),
            'entry_type' => $entryType,
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'cadence' => $cadence,
            'next_entry_date' => $this->faker->dateTimeBetween('-2 months', '+1 month')->format('Y-m-d'),
            'end_date' => $this->faker->optional()->dateTimeBetween('+2 months', '+2 years')?->format('Y-m-d'),
            'reference' => $this->faker->optional()->words(3, true),
            'notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'last_generated_at' => null,
        ];
    }
}
