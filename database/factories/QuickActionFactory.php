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
            'name' => $this->faker->words(2, true),
            'entry_target' => 'expense',
            'amount' => $this->faker->randomFloat(2, 1, 100),
            'fuel_volume' => null,
            'fuel_full_tank' => true,
            'mileage_locations' => null,
            'mileage_distance' => null,
            'vendor' => $this->faker->company(),
            'notes' => $this->faker->sentence(),
            'tags' => [$this->faker->word()],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
