<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Car Expense Tracker')
        ->assertSee('Track the real cost of running your car.')
        ->assertSee('Log In');
});
