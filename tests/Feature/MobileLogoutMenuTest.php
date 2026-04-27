<?php

use App\Models\User;

test('sidebar account menu keeps the desktop logout hidden on mobile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('hidden lg:block')
        ->toContain('action="'.route('logout').'"');
});
