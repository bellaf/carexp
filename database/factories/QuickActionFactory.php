<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuickAction>
 */
class QuickActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'car_id' => null,
            'expense_category_id' => ExpenseCategory::factory(),
            'name' => fake()->words(2, true),
            'entry_target' => 'expense',
            'amount' => fake()->randomFloat(2, 1, 100),
            'fuel_volume' => null,
            'fuel_full_tank' => true,
            'vendor' => fake()->company(),
            'notes' => fake()->sentence(),
            'tags' => [fake()->word()],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
