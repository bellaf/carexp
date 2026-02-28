<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('login', absolute: false));

    $this->assertGuest();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'is_admin' => 0,
        'is_approved' => 0,
    ]);
});
