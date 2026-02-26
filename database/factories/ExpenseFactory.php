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
            'amount' => fake()->randomFloat(2, 5, 1200),
            'expense_date' => fake()->date(),
            'odometer' => fake()->optional()->numberBetween(0, 220000),
            'vendor' => fake()->optional()->company(),
            'notes' => fake()->optional()->sentence(),
            'tags' => fake()->optional()->randomElements(['business', 'personal', 'reimbursable'], fake()->numberBetween(1, 2)),
        ];
    }
}
