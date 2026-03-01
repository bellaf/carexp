<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Livewire\Livewire;

test('guests are redirected to login from accounts page', function () {
    $this->get(route('accounts.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view accounts page', function () {
    $user = User::factory()->create();
    $this->seed(AccountSeeder::class);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertSee('Accounts')
        ->assertSee('System Accounts')
        ->assertSee('Custom Accounts');
});

test('user can rename a system account without changing its key', function () {
    $user = User::factory()->create();
    $this->seed(AccountSeeder::class);
    $fuelAccount = Account::query()->where('key', 'fuel_expense')->firstOrFail();

    $this->actingAs($user);

    Livewire::test('pages::accounts.index')
        ->call('editAccount', $fuelAccount->id)
        ->set('form.name', 'Petrol Spend')
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($fuelAccount->fresh()->name)->toBe('Petrol Spend')
        ->and($fuelAccount->fresh()->key)->toBe('fuel_expense');

    Livewire::test('pages::reimbursements.index')
        ->assertHasNoErrors();

    Livewire::test('pages::recurring.index')
        ->assertHasNoErrors();

    expect($fuelAccount->fresh()->name)->toBe('Petrol Spend');
});

test('user can create a custom income account and it is scoped to that user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->seed(AccountSeeder::class);

    Account::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other User Income',
        'key' => 'other_user_income',
        'group' => 'income',
        'is_system' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::accounts.index')
        ->call('startCreating')
        ->set('form.name', 'Private Mileage')
        ->set('form.group', 'income')
        ->set('form.is_active', true)
        ->call('saveAccount')
        ->assertHasNoErrors();

    $customAccount = Account::query()
        ->where('user_id', $user->id)
        ->where('name', 'Private Mileage')
        ->firstOrFail();

    expect($customAccount->is_system)->toBeFalse()
        ->and($customAccount->group)->toBe('income');

    Livewire::test('pages::reimbursements.index')
        ->assertSee('Private Mileage')
        ->assertDontSee('Other User Income');
});

test('used custom accounts cannot be deleted', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Allowance Plus',
        'key' => 'allowance_plus_income',
        'group' => 'income',
        'is_system' => false,
        'is_active' => true,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'income',
        'amount' => 50,
        'source_type' => 'reimbursement',
        'source_id' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::accounts.index')
        ->call('editAccount', $account->id)
        ->call('confirmDeleteEditing')
        ->assertSet('confirmingDelete', false)
        ->assertSet('deleteGuardMessage', 'This account is already used in ledger entries or recurring schedules. Archive it instead of deleting it.');

    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});

test('unused custom accounts can be archived and deleted', function () {
    $user = User::factory()->create();

    $account = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Temporary Income',
        'key' => 'temporary_income',
        'group' => 'income',
        'is_system' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::accounts.index')
        ->call('editAccount', $account->id)
        ->set('form.is_active', false)
        ->call('saveAccount')
        ->assertHasNoErrors();

    expect($account->fresh()->is_active)->toBeFalse();

    Livewire::test('pages::accounts.index')
        ->call('editAccount', $account->id)
        ->call('confirmDeleteEditing')
        ->assertSet('confirmingDelete', true)
        ->call('deleteEditingAccount')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});
