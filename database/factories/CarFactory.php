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
            'nickname' => $this->faker->optional()->word(),
            'year' => $this->faker->numberBetween(1998, (int) date('Y')),
            'make' => $this->faker->randomElement(['Honda', 'Toyota', 'Ford', 'Tesla', 'Hyundai']),
            'model' => $this->faker->randomElement(['Civic', 'Corolla', 'F-150', 'Model 3', 'Elantra']),
            'trim' => $this->faker->optional()->randomElement(['Base', 'Sport', 'Limited']),
            'vin' => $this->faker->optional()->regexify('[A-HJ-NPR-Z0-9]{17}'),
            'plate' => $this->faker->optional()->bothify('???-####'),
            'fuel_type' => $this->faker->randomElement(['gasoline', 'diesel', 'hybrid', 'electric']),
            'purchase_date' => $this->faker->optional()->date(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 2000, 80000),
            'purchase_odometer' => $this->faker->optional()->numberBetween(0, 180000),
            'current_odometer' => $this->faker->optional()->numberBetween(0, 220000),
            'sale_date' => null,
            'sale_price' => null,
            'sale_odometer' => null,
            'is_archived' => false,
            'is_default' => false,
        ];
    }
}
