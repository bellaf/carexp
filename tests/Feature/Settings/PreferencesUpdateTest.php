<?php

use App\Models\User;
use Livewire\Livewire;

test('preferences page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('preferences.edit'))
        ->assertOk()
        ->assertSee('Preferences');
});

test('preferences can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.preferences')
        ->set('preferred_currency', 'GBP')
        ->set('measurement_system', 'metric')
        ->set('volume_unit', 'liters')
        ->call('updatePreferences');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->preferred_currency)->toBe('GBP')
        ->and($user->measurement_system)->toBe('metric')
        ->and($user->volume_unit)->toBe('liters');
});
