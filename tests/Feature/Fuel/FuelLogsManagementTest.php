<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from fuel logs page', function () {
    $this->get(route('fuel.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view fuel logs page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('fuel.index'))
        ->assertOk()
        ->assertSee('Fuel Logs')
        ->assertSee('Tap any fuel entry to edit it.');
});

test('user can create fuel log with auto calculated values and linked ledger entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'odometer' => 10000,
        'volume' => 10,
        'price_per_unit' => 4.000,
        'full_tank' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->format('Y-m-d'))
        ->set('form.odometer', '10300')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '45.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', true)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    $fuelLog = FuelLog::query()
        ->where('user_id', $user->id)
        ->where('odometer', 10300)
        ->first();

    expect($fuelLog)->not->toBeNull();
    expect((float) $fuelLog->price_per_unit)->toBe(4.5)
        ->and((float) $fuelLog->calculated_efficiency)->toBe(30.0)
        ->and($fuelLog->ledger_entry_id)->not->toBeNull();

    $this->assertDatabaseHas('ledger_entries', [
        'id' => $fuelLog->ledger_entry_id,
        'user_id' => $user->id,
        'entry_type' => 'expense',
        'source_type' => 'fuel_log',
        'source_id' => $fuelLog->id,
        'amount' => '45.00',
    ]);
});

test('fuel log full tank value is normalized from string input', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->format('Y-m-d'))
        ->set('form.odometer', '10300')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '45.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', 'true')
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    $fuelLog = FuelLog::query()
        ->where('user_id', $user->id)
        ->where('odometer', 10300)
        ->firstOrFail();

    expect((bool) $fuelLog->full_tank)->toBeTrue();
});

test('new fuel log defaults odometer from latest known car reading', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 12000,
    ]);

    $user->mileageLogs()->create([
        'car_id' => $car->id,
        'log_date' => now()->subDay()->toDateString(),
        'purpose' => 'Work',
        'distance' => 145,
        'start_odometer' => 12100,
        'end_odometer' => 12345,
        'is_business' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->assertSet('form.car_id', (string) $car->id)
        ->assertSet('form.odometer', '12345');
});

test('changing car while creating fuel log refreshes the default odometer', function () {
    $user = User::factory()->create();
    $firstCar = Car::factory()->for($user)->create([
        'current_odometer' => 15000,
    ]);
    $secondCar = Car::factory()->for($user)->create([
        'current_odometer' => 22000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $firstCar->id)
        ->assertSet('form.odometer', '15000')
        ->set('form.car_id', (string) $secondCar->id)
        ->assertSet('form.odometer', '22000');
});

test('deleting fuel log also removes linked ledger entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $fuelLog = FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => null,
    ]);

    $fuelLog->update([
        'ledger_entry_id' => $user->ledgerEntries()->create([
            'car_id' => $car->id,
            'account_id' => Account::query()->firstOrCreate(
                ['key' => 'fuel_expense'],
                ['user_id' => null, 'name' => 'Fuel', 'group' => 'expense', 'is_system' => true, 'is_active' => true],
            )->id,
            'entry_date' => now()->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => 40,
            'source_type' => 'fuel_log',
            'source_id' => $fuelLog->id,
        ])->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('deleteFuelLog', $fuelLog->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('fuel_logs', ['id' => $fuelLog->id]);
    $this->assertDatabaseMissing('ledger_entries', ['id' => $fuelLog->ledger_entry_id]);
});

test('imperial distance with litres volume calculates mpg using conversion', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'litres',
    ]);
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'litres',
        'full_tank' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->format('Y-m-d'))
        ->set('form.odometer', '10100')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '40.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', true)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    $fuelLog = FuelLog::query()
        ->where('user_id', $user->id)
        ->where('odometer', 10100)
        ->firstOrFail();

    expect($fuelLog->volume_unit)->toBe('litres');
    expect((float) $fuelLog->calculated_efficiency)->toBe(round(100 / (10 / 4.54609), 3));
});

test('car current odometer syncs from most recent fuel log on create update and delete', function () {
    $user = User::factory()->create();
    $carA = Car::factory()->for($user)->create(['current_odometer' => null]);
    $carB = Car::factory()->for($user)->create(['current_odometer' => null]);

    $olderLog = FuelLog::factory()->for($carA)->create([
        'user_id' => $user->id,
        'log_date' => now()->subDays(5)->toDateString(),
        'odometer' => 12000,
        'ledger_entry_id' => null,
    ]);

    $latestLog = FuelLog::factory()->for($carA)->create([
        'user_id' => $user->id,
        'log_date' => now()->subDays(1)->toDateString(),
        'odometer' => 12345,
        'ledger_entry_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $carA->id)
        ->set('form.log_date', now()->toDateString())
        ->set('form.odometer', '12500')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '42.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', false)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    expect($carA->refresh()->current_odometer)->toBe(12500);

    Livewire::test('pages::fuel.index')
        ->call('editFuelLog', $latestLog->id)
        ->set('form.car_id', (string) $carB->id)
        ->set('form.log_date', now()->subDays(2)->toDateString())
        ->set('form.odometer', '13000')
        ->set('form.volume', '9.500')
        ->set('form.total_cost', '39.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', false)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    expect($carA->refresh()->current_odometer)->toBe(12500)
        ->and($carB->refresh()->current_odometer)->toBe(13000);

    Livewire::test('pages::fuel.index')
        ->call('deleteFuelLog', $olderLog->id)
        ->assertHasNoErrors();

    expect($carA->refresh()->current_odometer)->toBe(12500);
});

test('efficiency only calculates when current and immediately previous entries are full tank', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'gallons',
    ]);
    $car = Car::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->subDays(3)->toDateString())
        ->set('form.odometer', '10000')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '40.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', true)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->subDays(2)->toDateString())
        ->set('form.odometer', '10100')
        ->set('form.volume', '5.000')
        ->set('form.total_cost', '22.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', false)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    Livewire::test('pages::fuel.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.log_date', now()->subDay()->toDateString())
        ->set('form.odometer', '10200')
        ->set('form.volume', '10.000')
        ->set('form.total_cost', '44.00')
        ->set('form.price_per_unit', '')
        ->set('form.full_tank', true)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    $latestLog = FuelLog::query()->where('user_id', $user->id)->where('odometer', 10200)->firstOrFail();
    expect($latestLog->calculated_efficiency)->toBeNull();

    $middleLog = FuelLog::query()->where('user_id', $user->id)->where('odometer', 10100)->firstOrFail();

    Livewire::test('pages::fuel.index')
        ->call('editFuelLog', $middleLog->id)
        ->set('form.full_tank', true)
        ->call('saveFuelLog')
        ->assertHasNoErrors();

    expect((float) $middleLog->refresh()->calculated_efficiency)->toBe(20.0);
    expect((float) $latestLog->refresh()->calculated_efficiency)->toBe(10.0);
});

test('fuel log page shows weighted average efficiency across full tank intervals', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'gallons',
    ]);
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-02-01',
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => null,
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-02-02',
        'odometer' => 10100,
        'volume' => 5,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 20,
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-02-03',
        'odometer' => 10600,
        'volume' => 10,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 50,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::fuel.index')
        ->set('filterPeriod', 'all_time')
        ->assertSee('40.000')
        ->assertDontSee('35.000');
});

test('fuel logs page orders entries by odometer so displayed efficiency matches the previous row', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'gallons',
    ]);
    $car = Car::factory()->for($user)->create();

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-03-03',
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => null,
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => '2026-03-01',
        'odometer' => 10200,
        'volume' => 10,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('fuel.index'))
        ->assertOk()
        ->assertSeeInOrder([
            '10,200',
            '20.000',
            '10,000',
            'N/A',
        ]);
});
