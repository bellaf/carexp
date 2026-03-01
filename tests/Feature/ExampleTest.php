<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Car Expense Tracker')
        ->assertSee('Track the real cost of running your car.')
        ->assertSee('Log In')
        ->assertSee('rel="apple-touch-icon" href="/apple-touch-icon.png"', false)
        ->assertSee('name="apple-mobile-web-app-capable" content="yes"', false)
        ->assertSee('name="apple-mobile-web-app-title"', false);
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
