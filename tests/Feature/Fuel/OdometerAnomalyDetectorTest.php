<?php

use App\Models\Car;
use App\Models\FuelLog;
use App\Models\User;
use App\Support\OdometerAnomalyDetector;

function createFuelHistory(Car $car, User $user): void
{
    foreach ([10000, 10300, 10600, 10900, 11200] as $index => $odometer) {
        FuelLog::factory()->for($car)->create([
            'user_id' => $user->id,
            'log_date' => now()->subDays(10 - $index)->toDateString(),
            'odometer' => $odometer,
        ]);
    }
}

test('normal odometer interval passes without a warning', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    createFuelHistory($car, $user);

    $result = app(OdometerAnomalyDetector::class)->analyze(
        $car,
        now()->toDateString(),
        11520,
    );

    expect($result)
        ->status->toBe('ok')
        ->distance->toBe(320);
});

test('wild interval is returned as a confirmable warning using the recent median', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    createFuelHistory($car, $user);

    $result = app(OdometerAnomalyDetector::class)->analyze(
        $car,
        now()->toDateString(),
        13000,
    );

    expect($result)
        ->status->toBe('warning')
        ->distance->toBe(1800)
        ->typical_interval->toBe(300.0)
        ->fingerprint->not->toBeNull()
        ->and($result['message'])->toContain('recent typical fill-up interval is 300');
});

test('reading below the previous chronological entry is rejected', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    createFuelHistory($car, $user);

    $result = app(OdometerAnomalyDetector::class)->analyze(
        $car,
        now()->toDateString(),
        11199,
    );

    expect($result)
        ->status->toBe('error')
        ->fingerprint->toBeNull()
        ->and($result['message'])->toContain('cannot be lower than the previous reading');
});

test('backdated reading above the following chronological entry is rejected', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-01-01',
        'odometer' => 10000,
    ]);
    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-01-10',
        'odometer' => 10500,
    ]);

    $result = app(OdometerAnomalyDetector::class)->analyze(
        $car,
        '2026-01-05',
        10600,
    );

    expect($result)
        ->status->toBe('error')
        ->next_odometer->toBe(10500)
        ->and($result['message'])->toContain('cannot be higher than the following reading');
});
