<?php

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\FuelLog;
use App\Models\MileageLog;
use App\Models\QuickAction;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from quick actions page', function () {
    $this->get(route('quick-actions.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can create quick actions', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create([
        'key' => 'tolls',
        'name' => 'Tolls',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::quick-actions.index')
        ->call('startCreating')
        ->set('form.name', 'Dartford Toll Return')
        ->set('form.expense_category_id', (string) $category->id)
        ->set('form.car_id', (string) $car->id)
        ->set('form.amount', '5.00')
        ->set('form.vendor', 'Dartford Crossing')
        ->set('form.notes', 'Auto posted')
        ->set('form.tags', 'toll, road')
        ->set('form.is_active', true)
        ->set('form.sort_order', '1')
        ->call('saveQuickAction')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('quick_actions', [
        'user_id' => $user->id,
        'name' => 'Dartford Toll Return',
        'expense_category_id' => $category->id,
        'car_id' => $car->id,
        'amount' => '5.00',
        'is_active' => 1,
        'sort_order' => 1,
    ]);
});

test('authenticated users can create quick actions with empty amount defaulting to zero', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'is_default' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::quick-actions.index')
        ->call('startCreating')
        ->set('form.name', 'Variable Parking')
        ->set('form.car_id', (string) $car->id)
        ->set('form.amount', '')
        ->set('form.is_active', true)
        ->call('saveQuickAction')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('quick_actions', [
        'user_id' => $user->id,
        'name' => 'Variable Parking',
        'car_id' => $car->id,
        'amount' => '0.00',
        'is_active' => 1,
    ]);
});

test('quick action row edit flow opens modal without row action buttons', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create();
    $quickAction = QuickAction::factory()->for($user)->create([
        'expense_category_id' => $category->id,
        'name' => 'Row Click Edit',
        'amount' => 3.25,
    ]);

    $this->actingAs($user)
        ->get(route('quick-actions.index'))
        ->assertOk()
        ->assertSee('Row Click Edit');

    Livewire::test('pages::quick-actions.index')
        ->call('editQuickAction', $quickAction->id)
        ->assertSet('showForm', true)
        ->assertSet('editingQuickActionId', $quickAction->id)
        ->assertSet('form.name', 'Row Click Edit');
});

test('authenticated users can view quick actions page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('quick-actions.index'))
        ->assertOk()
        ->assertSee('Quick Actions')
        ->assertSee('Add Quick Action');
});

test('dashboard quick action button posts expense and ledger entry', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'tolls', 'name' => 'Tolls']);

    MileageLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => now()->subDay()->toDateString(),
        'start_odometer' => 35100,
        'end_odometer' => 35225,
    ]);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'name' => 'Dartford Toll Single',
        'amount' => 2.50,
        'vendor' => 'Dartford Crossing',
        'notes' => 'Quick posted',
        'tags' => ['toll'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Dartford Toll Single');

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('expenses', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'amount' => '2.50',
        'odometer' => 35225,
        'vendor' => 'Dartford Crossing',
    ]);

    $tollsAccount = Account::query()->where('key', 'tolls_expense')->first();

    expect($tollsAccount)->not->toBeNull();

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $tollsAccount->id,
        'entry_type' => 'expense',
        'amount' => '2.50',
        'source_type' => 'expense',
        'reference' => 'Dartford Crossing',
    ]);
});

test('dashboard quick action with zero amount accepts posted amount override', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'parking', 'name' => 'Parking']);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'name' => 'Car Park',
        'amount' => 0,
        'vendor' => 'Town Car Park',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction), [
            'amount' => '6.40',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('expenses', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'amount' => '6.40',
        'vendor' => 'Town Car Park',
    ]);

    $parkingAccount = Account::query()->where('key', 'parking_expense')->first();

    expect($parkingAccount)->not->toBeNull();

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $parkingAccount->id,
        'entry_type' => 'expense',
        'amount' => '6.40',
        'source_type' => 'expense',
        'reference' => 'Town Car Park',
    ]);
});

test('dashboard quick action with zero amount requires posted amount override', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'parking', 'name' => 'Parking']);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'name' => 'Car Park',
        'amount' => 0,
        'vendor' => 'Town Car Park',
        'is_active' => true,
    ]);

    $this->from(route('dashboard'))
        ->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('expenses', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'vendor' => 'Town Car Park',
    ]);
});

test('dashboard fuel quick action posts fuel log and ledger entry', function () {
    $user = User::factory()->create([
        'measurement_system' => 'metric',
        'volume_unit' => 'litres',
    ]);
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35200,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'fuel', 'name' => 'Fuel']);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'entry_target' => 'fuel_log',
        'name' => 'Quick Fuel',
        'amount' => 0,
        'fuel_volume' => 0,
        'fuel_full_tank' => true,
        'notes' => 'Forecourt quick log',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction), [
            'amount' => '40.00',
            'fuel_volume' => '30.000',
            'odometer' => 35500,
        ])
        ->assertRedirect(route('dashboard'));

    $fuelLog = FuelLog::query()->where('user_id', $user->id)->firstOrFail();

    expect((int) $fuelLog->car_id)->toBe($car->id)
        ->and((int) $fuelLog->odometer)->toBe(35500)
        ->and((string) $fuelLog->volume)->toBe('30.000')
        ->and((string) $fuelLog->volume_unit)->toBe('litres')
        ->and((bool) $fuelLog->full_tank)->toBeTrue();

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'entry_type' => 'expense',
        'amount' => '40.00',
        'source_type' => 'fuel_log',
        'source_id' => $fuelLog->id,
    ]);
});

test('dashboard fuel quick action can unset full tank flag at run time', function () {
    $user = User::factory()->create([
        'measurement_system' => 'metric',
        'volume_unit' => 'litres',
    ]);
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35200,
        'is_default' => true,
    ]);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'entry_target' => 'fuel_log',
        'name' => 'Quick Fuel Partial',
        'amount' => 25.00,
        'fuel_volume' => 20.000,
        'fuel_full_tank' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction), [
            'odometer' => 35600,
            'full_tank' => 0,
        ])
        ->assertRedirect(route('dashboard'));

    $fuelLog = FuelLog::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    expect((int) $fuelLog->car_id)->toBe($car->id)
        ->and((int) $fuelLog->odometer)->toBe(35600)
        ->and((bool) $fuelLog->full_tank)->toBeFalse();
});

test('dashboard fuel quick action defaults odometer from latest known car reading', function () {
    $user = User::factory()->create([
        'measurement_system' => 'metric',
        'volume_unit' => 'litres',
    ]);
    $car = $user->cars()->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'year' => 2018,
        'current_odometer' => 35200,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create(['key' => 'fuel', 'name' => 'Fuel']);

    $user->mileageLogs()->create([
        'car_id' => $car->id,
        'log_date' => now()->subDay()->toDateString(),
        'purpose' => 'Work',
        'distance' => 145,
        'start_odometer' => 35100,
        'end_odometer' => 35225,
        'is_business' => true,
    ]);

    $quickAction = QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'entry_target' => 'fuel_log',
        'name' => 'Quick Fuel',
        'amount' => 40.00,
        'fuel_volume' => 30.000,
        'fuel_full_tank' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.quick-actions.run', $quickAction))
        ->assertRedirect(route('dashboard'));

    $fuelLog = FuelLog::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    expect((int) $fuelLog->odometer)->toBe(35225);
});
