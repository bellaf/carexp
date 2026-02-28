<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\Reimbursement;
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

test('user can create reimbursement and assign reimbursement type', function () {
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

    $this->assertDatabaseHas('reimbursements', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'notes' => 'Monthly allowance',
    ]);

    $reimbursement = Reimbursement::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->where('notes', 'Monthly allowance')
        ->firstOrFail();

    expect($reimbursement->ledger_entry_id)->not->toBeNull();
    $this->assertDatabaseHas('ledger_entries', [
        'id' => $reimbursement->ledger_entry_id,
        'user_id' => $user->id,
        'account_id' => $allowanceAccount->id,
        'amount' => '75.00',
        'entry_type' => 'income',
        'source_type' => 'reimbursement',
        'source_id' => $reimbursement->id,
    ]);
});

test('user can update and delete reimbursement', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $reimbursement = Reimbursement::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $user->ledgerEntries()->create([
            'car_id' => $car->id,
            'account_id' => Account::query()->firstOrCreate(
                ['key' => 'company_car_allowance_income'],
                ['user_id' => null, 'name' => 'Company Car Allowance', 'group' => 'income', 'is_system' => true, 'is_active' => true],
            )->id,
            'entry_date' => now()->format('Y-m-d'),
            'entry_type' => 'income',
            'amount' => 50,
            'source_type' => 'reimbursement',
            'source_id' => 1,
        ])->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::reimbursements.index')
        ->call('editReimbursement', $reimbursement->id)
        ->set('form.amount', '120.00')
        ->call('saveReimbursement')
        ->assertHasNoErrors()
        ->call('deleteReimbursement', $reimbursement->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('reimbursements', ['id' => $reimbursement->id]);
});
