<?php

use App\Models\ExpenseCategory;
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
    $category = ExpenseCategory::factory()->create();

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
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::quick-actions.index')
        ->call('startCreating')
        ->set('form.name', 'Variable Parking')
        ->set('form.expense_category_id', (string) $category->id)
        ->set('form.car_id', (string) $car->id)
        ->set('form.amount', '')
        ->set('form.is_active', true)
        ->call('saveQuickAction')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('quick_actions', [
        'user_id' => $user->id,
        'name' => 'Variable Parking',
        'expense_category_id' => $category->id,
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
        'vendor' => 'Dartford Crossing',
    ]);

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
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
        'amount' => '6.40',
        'vendor' => 'Town Car Park',
    ]);

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'car_id' => $car->id,
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
