<?php

use App\Models\Car;
use App\Models\FuelLog;
use App\Models\User;

test('command recalculates stored fuel efficiencies using imperial gallon conversion', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'liters',
    ]);
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'liters',
        'full_tank' => true,
        'calculated_efficiency' => 12.345,
    ]);

    $fuelLog = FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'odometer' => 10100,
        'volume' => 10,
        'volume_unit' => 'liters',
        'full_tank' => true,
        'calculated_efficiency' => 20.000,
    ]);

    $this->artisan('app:recalculate-fuel-efficiencies')
        ->expectsOutput('Fuel efficiency recalculation complete.')
        ->assertSuccessful();

    expect((float) $fuelLog->refresh()->calculated_efficiency)
        ->toBe(round(100 / (10 / 4.54609), 3));
});

test('command can be limited to a single car', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $firstCar = Car::factory()->for($user)->create();
    $secondCar = Car::factory()->for($user)->create();

    FuelLog::factory()->for($firstCar)->create([
        'user_id' => $user->id,
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 1.000,
    ]);

    $firstCarLog = FuelLog::factory()->for($firstCar)->create([
        'user_id' => $user->id,
        'odometer' => 10100,
        'volume' => 5,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 1.000,
    ]);

    FuelLog::factory()->for($secondCar)->create([
        'user_id' => $user->id,
        'odometer' => 20000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 2.000,
    ]);

    $secondCarLog = FuelLog::factory()->for($secondCar)->create([
        'user_id' => $user->id,
        'odometer' => 20100,
        'volume' => 5,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 2.000,
    ]);

    $this->artisan('app:recalculate-fuel-efficiencies', ['--car-id' => $firstCar->id])
        ->assertSuccessful();

    expect((float) $firstCarLog->refresh()->calculated_efficiency)->toBe(20.0)
        ->and((float) $secondCarLog->refresh()->calculated_efficiency)->toBe(2.0);
});
