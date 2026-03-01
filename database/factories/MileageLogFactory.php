<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MileageLog>
 */
class MileageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $startOdometer = $this->faker->numberBetween(1000, 200000);

        return [
            'user_id' => $user,
            'car_id' => Car::factory()->for($user),
            'log_date' => $this->faker->date(),
            'start_odometer' => $startOdometer,
            'end_odometer' => $startOdometer + $this->faker->numberBetween(5, 150),
            'locations' => implode(', ', $this->faker->words($this->faker->numberBetween(1, 3))),
        ];
    }
}
