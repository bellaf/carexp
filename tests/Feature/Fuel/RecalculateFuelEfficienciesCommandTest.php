<?php

use App\Models\Car;
use App\Models\FuelLog;
use App\Models\User;

test('command recalculates stored fuel efficiencies using imperial gallon conversion', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'litres',
    ]);
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-01-01',
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'litres',
        'full_tank' => true,
        'calculated_efficiency' => 12.345,
    ]);

    $fuelLog = FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-02-01',
        'odometer' => 10100,
        'volume' => 10,
        'volume_unit' => 'litres',
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
        'log_date' => '2026-01-01',
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 1.000,
    ]);

    $firstCarLog = FuelLog::factory()->for($firstCar)->create([
        'user_id' => $user->id,
        'log_date' => '2026-02-01',
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

test('combined intervals weight averages by all fuel and keep cars separate', function () {
    $user = User::factory()->create(['measurement_system' => 'metric']);
    $car = Car::factory()->for($user)->create();
    foreach ([[null, 20, true], [10000, 20, true], [null, 40, false], [null, 20, true], [10600, 40, true], [10800, 10, true]] as $index => [$odometer, $volume, $fullTank]) {
        FuelLog::factory()->for($car)->create([
            'log_date' => '2026-01-0'.($index + 1), 'odometer' => $odometer,
            'volume' => $volume, 'volume_unit' => 'litres', 'full_tank' => $fullTank,
            'calculated_efficiency' => 99,
        ]);
    }
    $otherCar = Car::factory()->for($user)->create();
    $otherLog = FuelLog::factory()->for($otherCar)->create(['odometer' => null, 'calculated_efficiency' => null]);
    $this->artisan('app:recalculate-fuel-efficiencies')->assertSuccessful();
    $logs = $car->fuelLogs()->orderBy('log_date')->get();
    expect($logs[0]->calculated_efficiency)->toBeNull()
        ->and($logs[1]->calculated_efficiency)->toBeNull()
        ->and($logs[2]->calculated_efficiency)->toBeNull()
        ->and($logs[3]->calculated_efficiency)->toBeNull()
        ->and((float) $logs[4]->calculated_efficiency)->toBe(6.0)
        ->and($logs[4]->efficiency_fill_count)->toBe(3)
        ->and($logs[4]->efficiency_volume)->toBe(100.0)
        ->and(\App\Support\FuelEfficiencyCalculator::averageForLogs($logs, 'metric'))->toBe(round(800 / 110, 3))
        ->and($otherLog->refresh()->calculated_efficiency)->toBeNull();
});
