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
        $entryType = $this->faker->randomElement(['expense', 'income']);

        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'account_id' => Account::factory()->state([
                'group' => $entryType,
            ]),
            'entry_date' => $this->faker->date(),
            'entry_type' => $entryType,
            'amount' => $this->faker->randomFloat(2, 5, 2000),
            'source_type' => $this->faker->optional()->randomElement(['manual', 'fuel_log', 'maintenance_record', 'reimbursement']),
            'source_id' => $this->faker->optional()->numberBetween(1, 99999),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
