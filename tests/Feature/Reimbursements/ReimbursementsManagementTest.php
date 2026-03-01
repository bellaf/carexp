<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\LedgerEntry;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from reimbursements page', function () {
    $this->get(route('reimbursements.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view reimbursements page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('reimbursements.index'))
        ->assertOk()
        ->assertSee('Reimbursements')
        ->assertSee('Tap any reimbursement to edit it.');
});

test('user can create reimbursement as a ledger income entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $allowanceAccount = Account::query()->updateOrCreate(
        ['key' => 'company_car_allowance_income'],
        [
            'user_id' => null,
            'name' => 'Company Car Allowance',
            'group' => 'income',
            'is_system' => true,
            'is_active' => true,
        ],
    );

    $this->actingAs($user);

    Livewire::test('pages::reimbursements.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.account_id', (string) $allowanceAccount->id)
        ->set('form.reimbursed_date', now()->format('Y-m-d'))
        ->set('form.amount', '75.00')
        ->set('form.notes', 'Monthly allowance')
        ->call('saveReimbursement')
        ->assertHasNoErrors();

    $ledgerEntry = LedgerEntry::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->where('account_id', $allowanceAccount->id)
        ->where('entry_type', 'income')
        ->where('amount', '75.00')
        ->where('notes', 'Monthly allowance')
        ->firstOrFail();

    expect($ledgerEntry->source_type)->toBe('reimbursement')
        ->and($ledgerEntry->source_id)->toBeNull();

});

test('user can update and delete reimbursement ledger entries', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $incomeAccount = Account::query()->firstOrCreate(
        ['key' => 'company_car_allowance_income'],
        ['user_id' => null, 'name' => 'Company Car Allowance', 'group' => 'income', 'is_system' => true, 'is_active' => true],
    );

    $ledgerEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $incomeAccount->id,
        'entry_date' => now()->format('Y-m-d'),
        'entry_type' => 'income',
        'amount' => 50,
        'source_type' => 'reimbursement',
        'source_id' => null,
        'notes' => 'Allowance',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reimbursements.index')
        ->call('editReimbursement', $ledgerEntry->id)
        ->set('form.amount', '120.00')
        ->set('form.notes', 'Updated allowance')
        ->call('saveReimbursement')
        ->assertHasNoErrors()
        ->call('deleteReimbursement', $ledgerEntry->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('ledger_entries', ['id' => $ledgerEntry->id]);
});

test('recurring income ledger entries appear in reimbursements view', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $incomeAccount = Account::query()->firstOrCreate(
        ['key' => 'company_business_fuel_tolls_income'],
        ['user_id' => null, 'name' => 'Company Business Fuel & Tolls Reimbursement', 'group' => 'income', 'is_system' => true, 'is_active' => true],
    );

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $incomeAccount->id,
        'entry_date' => '2025-12-01',
        'entry_type' => 'income',
        'amount' => 45,
        'source_type' => 'recurring',
        'source_id' => 99,
        'notes' => 'Recurring repayment',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reimbursements.index')
        ->set('filterPeriod', 'all_time')
        ->assertSee('Company Business Fuel & Tolls Reimbursement')
        ->assertSee('Recurring repayment');
});
