<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Car>
 */
class CarFactory extends Factory
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
            'nickname' => fake()->optional()->word(),
            'year' => fake()->numberBetween(1998, (int) date('Y')),
            'make' => fake()->randomElement(['Honda', 'Toyota', 'Ford', 'Tesla', 'Hyundai']),
            'model' => fake()->randomElement(['Civic', 'Corolla', 'F-150', 'Model 3', 'Elantra']),
            'trim' => fake()->optional()->randomElement(['Base', 'Sport', 'Limited']),
            'vin' => fake()->optional()->regexify('[A-HJ-NPR-Z0-9]{17}'),
            'plate' => fake()->optional()->bothify('???-####'),
            'fuel_type' => fake()->randomElement(['gasoline', 'diesel', 'hybrid', 'electric']),
            'purchase_date' => fake()->optional()->date(),
            'purchase_price' => fake()->optional()->randomFloat(2, 2000, 80000),
            'purchase_odometer' => fake()->optional()->numberBetween(0, 180000),
            'current_odometer' => fake()->optional()->numberBetween(0, 220000),
            'is_archived' => false,
            'is_default' => false,
        ];
    }
}
