<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
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
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => $this->faker->randomFloat(2, 5, 1200),
            'expense_date' => $this->faker->date(),
            'odometer' => $this->faker->optional()->numberBetween(0, 220000),
            'vendor' => $this->faker->optional()->company(),
            'notes' => $this->faker->optional()->sentence(),
            'tags' => $this->faker->optional()->randomElements(['business', 'personal', 'reimbursable'], $this->faker->numberBetween(1, 2)),
        ];
    }
}
