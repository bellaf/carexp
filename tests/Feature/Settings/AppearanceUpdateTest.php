<?php

use App\Models\User;
use Livewire\Livewire;

test('appearance page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Appearance')
        ->assertSee('Warm Paper')
        ->assertSee('Soft Automotive')
        ->assertSee('Editorial Neutral');
});

test('appearance theme can be updated', function () {
    $user = User::factory()->create([
        'ui_theme' => 'classic',
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.appearance')
        ->set('ui_theme', 'warm-paper')
        ->call('updateTheme');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->ui_theme)->toBe('warm-paper');
});
