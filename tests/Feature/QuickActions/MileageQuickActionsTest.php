<?php

use App\Models\Car;
use App\Models\ExpenseCategory;
use App\Models\MileageLog;
use App\Models\QuickAction;
use App\Models\User;
use Livewire\Livewire;

test('users can create mileage quick actions with standard locations', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::quick-actions.index')
        ->call('startCreating')
        ->set('form.name', 'Office Loop')
        ->set('form.entry_target', 'mileage_log')
        ->set('form.car_id', (string) $car->id)
        ->set('form.mileage_distance', '36')
        ->set('form.mileage_locations', 'Office, Warehouse')
        ->set('form.sort_order', '2')
        ->call('saveQuickAction')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('quick_actions', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'entry_target' => 'mileage_log',
        'name' => 'Office Loop',
        'mileage_locations' => 'Office, Warehouse',
        'mileage_distance' => 36,
        'amount' => '0.00',
    ]);
});

test('dashboard mileage quick action posts mileage log using standard trip miles', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 15000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'other', 'name' => 'Other']);

    MileageLog::factory()->for($user)->for($car)->create([
        'log_date' => '2026-02-27',
        'start_odometer' => 14950,
        'end_odometer' => 15025,
    ]);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'entry_target' => 'mileage_log',
        'name' => 'Office Loop',
        'amount' => 0,
        'mileage_locations' => 'Office, Warehouse',
        'mileage_distance' => 36,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction), [
            'start_odometer' => 15025,
        ])
        ->assertRedirect(route('dashboard'));

    $mileageLog = MileageLog::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->where('start_odometer', 15025)
        ->where('end_odometer', 15061)
        ->firstOrFail();

    expect($mileageLog->log_date->toDateString())->toBe(now()->toDateString())
        ->and($mileageLog->locations)->toBe('Office, Warehouse');
});

test('dashboard mileage quick action defaults start odometer from latest known car reading', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 15000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'other', 'name' => 'Other']);

    $user->fuelLogs()->create([
        'car_id' => $car->id,
        'ledger_entry_id' => null,
        'log_date' => now()->subDay()->toDateString(),
        'odometer' => 15120,
        'volume' => 20,
        'volume_unit' => 'litres',
        'price_per_unit' => 1.5,
        'full_tank' => true,
        'calculated_efficiency' => null,
    ]);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'entry_target' => 'mileage_log',
        'name' => 'Office Loop',
        'amount' => 0,
        'mileage_locations' => 'Office, Warehouse',
        'mileage_distance' => 36,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction))
        ->assertRedirect(route('dashboard'));

    $mileageLog = MileageLog::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->latest('id')
        ->firstOrFail();

    expect((int) $mileageLog->start_odometer)->toBe(15120)
        ->and((int) $mileageLog->end_odometer)->toBe(15156);
});
