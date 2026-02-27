<?php

namespace Database\Factories;

use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reimbursement>
 */
class ReimbursementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'car_id' => Car::factory(),
            'user_id' => static function (array $attributes): int {
                return Car::query()->findOrFail($attributes['car_id'])->user_id;
            },
            'ledger_entry_id' => null,
            'reimbursed_date' => $this->faker->date(),
            'source' => $this->faker->optional()->randomElement(['Employer', 'Client Reimbursement']),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
