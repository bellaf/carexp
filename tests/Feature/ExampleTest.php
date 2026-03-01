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
