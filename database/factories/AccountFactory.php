<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->randomElement(['expense', 'income']);

        return [
            'user_id' => null,
            'name' => fake()->words(2, true),
            'key' => fake()->unique()->slug(),
            'group' => $group,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
