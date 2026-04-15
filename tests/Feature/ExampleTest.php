<?php

test('guests visiting the home route are redirected to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login', absolute: false));
});

test('authenticated users visiting the home route are redirected to the dashboard', function () {
    $response = $this->actingAs(\App\Models\User::factory()->create())->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('ships app-specific icon assets', function () {
    expect(public_path('favicon.svg'))->toBeFile();
    expect(public_path('apple-touch-icon.png'))->toBeFile();

    $favicon = file_get_contents(public_path('favicon.svg'));

    expect($favicon)
        ->toContain('Car Expense Tracker Icon')
        ->toContain('#14B8A6')
        ->not->toContain('#FF2D20');

    [$width, $height] = getimagesize(public_path('apple-touch-icon.png'));

    expect($width)->toBe(180)
        ->and($height)->toBe(180);
});
