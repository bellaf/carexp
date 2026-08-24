<?php

use App\Models\Car;
use App\Models\FuelLog;
use App\Models\User;

test('command imports fuel logs from csv and creates linked ledger entries', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
        'current_odometer' => null,
    ]);

    $csvPath = base_path('tests/fixtures/fuel-import.csv');
    @mkdir(dirname($csvPath), 0777, true);

    file_put_contents($csvPath, implode("\n", [
        'Date,Odo Read,litres,Cost (£),per l,MPG',
        '17/10/2024,26302,31.96,£41.52,£1.299,31',
        '22/10/2024,26540,35.06,£47.65,£1.359,',
        '13/12/2024,,27.42,£36.44,£1.329,',
    ]));

    $this->artisan('app:import-fuel-logs-from-csv', [
        '--file' => $csvPath,
        '--user-id' => $user->id,
        '--car-id' => $car->id,
        '--volume-unit' => 'litres',
        '--date-format' => 'd/m/Y',
    ])->assertSuccessful();

    $this->assertDatabaseCount('fuel_logs', 2);
    $this->assertDatabaseCount('ledger_entries', 2);
    $this->assertDatabaseHas('fuel_logs', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'odometer' => 26302,
        'volume' => '31.960',
    ]);
    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'entry_type' => 'expense',
        'amount' => '41.52',
        'source_type' => 'fuel_log',
    ]);

    expect($car->refresh()->current_odometer)->toBe(26540);

    unlink($csvPath);
});

test('command dry run validates rows without writing data', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
    ]);

    $csvPath = base_path('tests/fixtures/fuel-import-dry-run.csv');
    @mkdir(dirname($csvPath), 0777, true);

    file_put_contents($csvPath, implode("\n", [
        'Date,Odo Read,litres,Cost (£),per l,MPG',
        '02/03/2025,30925,36.58,£50.08,£1.369,42',
    ]));

    $this->artisan('app:import-fuel-logs-from-csv', [
        '--file' => $csvPath,
        '--user-id' => $user->id,
        '--car-id' => $car->id,
        '--dry-run' => true,
    ])->assertSuccessful();

    $this->assertDatabaseCount('fuel_logs', 0);
    $this->assertDatabaseCount('ledger_entries', 0);

    unlink($csvPath);
});

test('command skips wild odometer intervals unless they are explicitly allowed', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
        'current_odometer' => 11200,
    ]);

    foreach ([10000, 10300, 10600, 10900, 11200] as $index => $odometer) {
        FuelLog::factory()->for($car)->create([
            'user_id' => $user->id,
            'log_date' => '2026-01-0'.($index + 1),
            'odometer' => $odometer,
        ]);
    }

    $csvPath = base_path('tests/fixtures/fuel-import-anomaly.csv');
    @mkdir(dirname($csvPath), 0777, true);

    file_put_contents($csvPath, implode("\n", [
        'Date,Odo Read,litres,Cost (£),per l,MPG',
        '10/01/2026,13000,35.00,£47.25,£1.350,',
    ]));

    $arguments = [
        '--file' => $csvPath,
        '--user-id' => $user->id,
        '--car-id' => $car->id,
        '--volume-unit' => 'litres',
        '--date-format' => 'd/m/Y',
    ];

    $this->artisan('app:import-fuel-logs-from-csv', $arguments)
        ->assertSuccessful();

    $this->assertDatabaseMissing('fuel_logs', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'odometer' => 13000,
    ]);

    $this->artisan('app:import-fuel-logs-from-csv', [
        ...$arguments,
        '--allow-odometer-anomalies' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('fuel_logs', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'odometer' => 13000,
    ]);

    unlink($csvPath);
});
