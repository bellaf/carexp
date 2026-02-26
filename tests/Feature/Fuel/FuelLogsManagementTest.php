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
        ->assertSee('Fuel Logs');
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

test('imperial distance with liters volume calculates mpg using conversion', function () {
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

    expect($fuelLog->volume_unit)->toBe('liters');
    expect((float) $fuelLog->calculated_efficiency)->toBe(round(100 / (10 * 0.2641720524), 3));
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
