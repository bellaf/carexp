<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entryType = fake()->randomElement(['expense', 'income']);

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'account_id' => Account::factory()->state([
                'group' => $entryType,
            ]),
            'entry_date' => fake()->date(),
            'entry_type' => $entryType,
            'amount' => fake()->randomFloat(2, 5, 2000),
            'source_type' => fake()->optional()->randomElement(['manual', 'fuel_log', 'maintenance_record', 'reimbursement']),
            'source_id' => fake()->optional()->numberBetween(1, 99999),
            'reference' => fake()->optional()->bothify('REF-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
