<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('guests are redirected to login from recurring page', function () {
    $this->get(route('recurring.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view recurring page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('recurring.index'))
        ->assertOk()
        ->assertSee('Recurring Schedules')
        ->assertSee('Add Schedule');
});

test('user can create, update, pause, and delete a recurring schedule', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'group' => 'expense',
        'name' => 'Insurance',
        'key' => 'insurance_expense',
        'is_system' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::recurring.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.entry_type', 'expense')
        ->set('form.account_id', (string) $account->id)
        ->set('form.amount', '120.00')
        ->set('form.cadence', 'monthly')
        ->set('form.next_entry_date', now()->toDateString())
        ->set('form.reference', 'Insurance')
        ->call('saveRecurring')
        ->assertHasNoErrors();

    $scheduleId = RecurringTransaction::query()
        ->where('user_id', $user->id)
        ->where('account_id', $account->id)
        ->value('id');

    expect($scheduleId)->not->toBeNull();

    Livewire::test('pages::recurring.index')
        ->call('editRecurring', (int) $scheduleId)
        ->set('form.amount', '145.50')
        ->call('saveRecurring')
        ->assertHasNoErrors()
        ->call('toggleRecurringActive', (int) $scheduleId)
        ->call('deleteRecurring', (int) $scheduleId);

    $this->assertDatabaseMissing('recurring_transactions', [
        'id' => (int) $scheduleId,
    ]);
});

test('user can trigger recurring generation from recurring page in dev', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'group' => 'expense',
        'name' => 'Insurance',
        'key' => 'insurance_expense',
        'is_system' => true,
    ]);

    DB::table('recurring_transactions')->insert([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 75.00,
        'cadence' => 'monthly',
        'next_entry_date' => now()->subDay()->toDateString(),
        'end_date' => null,
        'reference' => 'Dev Run',
        'notes' => null,
        'is_active' => true,
        'last_generated_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::recurring.index')
        ->call('runDueEntriesNow');

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => '75.00',
        'source_type' => 'recurring',
        'reference' => 'Dev Run',
    ]);
});

test('user can skip next recurring occurrence from modal', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'group' => 'expense',
        'name' => 'Parking',
        'key' => 'parking_expense',
        'is_system' => true,
    ]);

    $schedule = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_entry_date' => '2026-03-15',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::recurring.index')
        ->call('openRecurringDetails', $schedule->id)
        ->call('skipNextOccurrence', $schedule->id)
        ->assertHasNoErrors();

    $schedule->refresh();

    expect($schedule->next_entry_date->format('Y-m-d'))->toBe('2026-04-15');
    expect($schedule->is_active)->toBeTrue();
});

test('recurring modal shows upcoming preview dates', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'group' => 'income',
        'name' => 'Allowance',
        'key' => 'company_car_allowance_income',
        'is_system' => true,
    ]);

    $schedule = RecurringTransaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'income',
        'cadence' => 'quarterly',
        'next_entry_date' => '2026-01-10',
        'end_date' => '2026-12-31',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::recurring.index')
        ->call('openRecurringDetails', $schedule->id)
        ->assertSee('Upcoming Preview')
        ->assertSee('10-01-2026')
        ->assertSee('10-04-2026')
        ->assertSee('10-07-2026')
        ->assertSee('10-10-2026');
});
