<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\User;
use App\Models\VehicleObligation;
use Livewire\Livewire;

test('guests are redirected to login from obligations page', function () {
    $this->get(route('obligations.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view obligations page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('obligations.index'))
        ->assertOk()
        ->assertSee('Obligations')
        ->assertSee('Tap a record to edit it.');
});

test('user can create obligation with linked ledger entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::obligations.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.obligation_type', 'insurance')
        ->set('form.provider', 'Admiral')
        ->set('form.reference', 'POL123')
        ->set('form.due_date', now()->addDays(20)->format('Y-m-d'))
        ->set('form.amount', '420.50')
        ->call('saveObligation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('vehicle_obligations', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'obligation_type' => 'insurance',
        'provider' => 'Admiral',
        'reference' => 'POL123',
    ]);

    $obligation = VehicleObligation::query()->where('user_id', $user->id)->firstOrFail();

    expect($obligation->ledger_entry_id)->not->toBeNull();

    $this->assertDatabaseHas('ledger_entries', [
        'id' => $obligation->ledger_entry_id,
        'user_id' => $user->id,
        'car_id' => $car->id,
        'entry_type' => 'expense',
        'source_type' => 'vehicle_obligation',
        'source_id' => $obligation->id,
        'amount' => '420.50',
    ]);

    $this->assertDatabaseHas('accounts', [
        'key' => 'insurance_expense',
        'name' => 'Insurance',
    ]);
});

test('user can update and delete obligation', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'tax_registration_expense',
        'name' => 'Tax/Registration',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $ledgerEntryId = $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_date' => now()->addDays(7)->toDateString(),
        'entry_type' => 'expense',
        'amount' => 190,
        'source_type' => 'vehicle_obligation',
        'source_id' => 999,
    ])->id;

    $obligation = VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $ledgerEntryId,
        'obligation_type' => 'tax',
        'provider' => 'DVLA',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::obligations.index')
        ->call('editObligation', $obligation->id)
        ->set('form.reference', 'VED-2026')
        ->set('form.amount', '210.00')
        ->call('saveObligation')
        ->assertHasNoErrors()
        ->call('deleteObligation', $obligation->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('vehicle_obligations', ['id' => $obligation->id]);
    $this->assertDatabaseMissing('ledger_entries', ['id' => $ledgerEntryId]);
});

test('user can renew obligation and create next years entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $obligation = VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'insurance',
        'provider' => 'Admiral',
        'reference' => 'POL123',
        'start_date' => '2026-03-01',
        'due_date' => '2027-02-28',
        'amount' => 420.50,
        'is_active' => true,
        'completed_at' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::obligations.index')
        ->call('renewObligation', $obligation->id)
        ->assertHasNoErrors();

    $obligation->refresh();

    expect($obligation->is_active)->toBeFalse();
    expect($obligation->completed_at)->not->toBeNull();

    $renewal = VehicleObligation::query()
        ->where('renewed_from_id', $obligation->id)
        ->firstOrFail();

    expect($renewal->car_id)->toBe($car->id);
    expect($renewal->obligation_type)->toBe('insurance');
    expect($renewal->provider)->toBe('Admiral');
    expect($renewal->reference)->toBe('POL123');
    expect($renewal->start_date?->format('Y-m-d'))->toBe('2027-03-01');
    expect($renewal->due_date->format('Y-m-d'))->toBe('2028-02-28');
    expect((float) $renewal->amount)->toBe(420.50);
    expect($renewal->is_active)->toBeTrue();
    expect($renewal->completed_at)->toBeNull();
    expect($renewal->ledger_entry_id)->toBeNull();
});
