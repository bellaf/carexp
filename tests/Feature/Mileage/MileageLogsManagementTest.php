<?php

use App\Models\Car;
use App\Models\MileageLog;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from mileage logs page', function () {
    $this->get(route('mileage.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view mileage logs page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('mileage.index'))
        ->assertOk()
        ->assertSee('Mileage Logs')
        ->assertSee('Add Mileage Log');
});

test('user can create mileage log with start odometer defaulted from latest prior end reading', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 12000,
        'is_default' => true,
    ]);

    MileageLog::factory()->for($user)->for($car)->create([
        'log_date' => '2026-02-27',
        'start_odometer' => 12000,
        'end_odometer' => 12042,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::mileage.index')
        ->call('startCreating')
        ->assertSet('form.car_id', (string) $car->id)
        ->assertSet('form.start_odometer', '12042')
        ->set('form.log_date', '2026-02-28')
        ->set('form.end_odometer', '12068')
        ->set('form.locations', 'Office, Depot')
        ->call('saveMileageLog')
        ->assertHasNoErrors();

    $mileageLog = MileageLog::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->where('start_odometer', 12042)
        ->where('end_odometer', 12068)
        ->firstOrFail();

    expect($mileageLog->log_date->toDateString())->toBe('2026-02-28')
        ->and($mileageLog->locations)->toBe('Office, Depot');
});

test('user can delete mileage log from edit modal', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $mileageLog = MileageLog::factory()->for($user)->for($car)->create();

    $this->actingAs($user);

    Livewire::test('pages::mileage.index')
        ->call('editMileageLog', $mileageLog->id)
        ->call('confirmDeleteEditing')
        ->call('deleteEditingMileageLog')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('mileage_logs', ['id' => $mileageLog->id]);
});
