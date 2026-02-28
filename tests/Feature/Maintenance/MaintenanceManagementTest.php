<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from maintenance page', function () {
    $this->get(route('maintenance.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view maintenance page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('maintenance.index'))
        ->assertOk()
        ->assertSee('Maintenance')
        ->assertSee('Tap a record to edit it.');
});

test('user can create maintenance record', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::maintenance.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.service_type_option', 'oil_change')
        ->set('form.service_date', now()->format('Y-m-d'))
        ->set('form.cost', '95.50')
        ->set('form.next_due_date', now()->addDays(10)->format('Y-m-d'))
        ->call('saveRecord')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('maintenance_records', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'service_type' => 'oil_change',
    ]);

    $record = MaintenanceRecord::query()
        ->where('user_id', $user->id)
        ->where('service_type', 'oil_change')
        ->firstOrFail();

    expect($record->ledger_entry_id)->not->toBeNull();
    $this->assertDatabaseHas('ledger_entries', [
        'id' => $record->ledger_entry_id,
        'user_id' => $user->id,
        'entry_type' => 'expense',
        'source_type' => 'maintenance_record',
        'source_id' => $record->id,
        'amount' => '95.50',
    ]);
});

test('user can update and delete maintenance record', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $record = MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'service_type' => 'Brake Check',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::maintenance.index')
        ->call('editRecord', $record->id)
        ->set('form.service_type_option', 'other')
        ->set('form.service_type_custom', 'Brake Service')
        ->set('form.cost', '150.00')
        ->call('saveRecord')
        ->assertHasNoErrors()
        ->call('deleteRecord', $record->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('maintenance_records', ['id' => $record->id]);
});

test('user can save custom service type via other option', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::maintenance.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.service_type_option', 'other')
        ->set('form.service_type_custom', 'Wheel Bearing Check')
        ->set('form.service_date', now()->format('Y-m-d'))
        ->call('saveRecord')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('maintenance_records', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'service_type' => 'Wheel Bearing Check',
    ]);
});

test('deleting maintenance record also removes linked ledger entry', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $record = MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => null,
    ]);

    $record->update([
        'ledger_entry_id' => $user->ledgerEntries()->create([
            'car_id' => $car->id,
            'account_id' => Account::query()->firstOrCreate(
                ['key' => 'maintenance_expense'],
                ['user_id' => null, 'name' => 'Maintenance', 'group' => 'expense', 'is_system' => true, 'is_active' => true],
            )->id,
            'entry_date' => now()->format('Y-m-d'),
            'entry_type' => 'expense',
            'amount' => 120,
            'source_type' => 'maintenance_record',
            'source_id' => $record->id,
        ])->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::maintenance.index')
        ->call('deleteRecord', $record->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('maintenance_records', ['id' => $record->id]);
    $this->assertDatabaseMissing('ledger_entries', ['id' => $record->ledger_entry_id]);
});
