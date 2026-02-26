<?php

use App\Models\Car;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from cars page', function () {
    $this->get(route('cars.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view cars page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cars.index'))
        ->assertOk()
        ->assertSee('Cars');
});

test('user can create a car', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::cars.index')
        ->call('startCreating')
        ->set('form.make', 'Toyota')
        ->set('form.model', 'Corolla')
        ->set('form.year', 2020)
        ->set('form.current_odometer', 42000)
        ->set('form.fuel_type', 'gasoline')
        ->call('saveCar')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cars', [
        'user_id' => $user->id,
        'make' => 'Toyota',
        'model' => 'Corolla',
        'year' => 2020,
        'is_default' => true,
    ]);
});

test('user can update and archive their car', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'make' => 'Honda',
        'model' => 'Civic',
        'is_archived' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cars.index')
        ->call('editCar', $car->id)
        ->set('form.model', 'Accord')
        ->call('saveCar')
        ->assertHasNoErrors()
        ->call('archiveCar', $car->id)
        ->assertHasNoErrors();

    $car->refresh();

    expect($car->model)->toBe('Accord')
        ->and($car->is_archived)->toBeTrue();
});

test('user can set a different car as current', function () {
    $user = User::factory()->create();
    $firstCar = Car::factory()->for($user)->create([
        'is_default' => true,
    ]);
    $secondCar = Car::factory()->for($user)->create([
        'is_default' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cars.index')
        ->call('setDefaultCar', $secondCar->id)
        ->assertHasNoErrors();

    $firstCar->refresh();
    $secondCar->refresh();

    expect($firstCar->is_default)->toBeFalse()
        ->and($secondCar->is_default)->toBeTrue();
});

test('archiving the current car assigns another active car as current', function () {
    $user = User::factory()->create();
    $currentCar = Car::factory()->for($user)->create([
        'is_default' => true,
    ]);
    $fallbackCar = Car::factory()->for($user)->create([
        'is_default' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::cars.index')
        ->call('archiveCar', $currentCar->id)
        ->assertHasNoErrors();

    $currentCar->refresh();
    $fallbackCar->refresh();

    expect($currentCar->is_archived)->toBeTrue()
        ->and($currentCar->is_default)->toBeFalse()
        ->and($fallbackCar->is_default)->toBeTrue();
});
