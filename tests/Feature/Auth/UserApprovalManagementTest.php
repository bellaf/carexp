<?php

use App\Models\User;
use Livewire\Livewire;

test('admin can view users page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Users');
});

test('non admin can not view users page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('admin can approve pending user', function () {
    $admin = User::factory()->admin()->create();
    $pendingUser = User::factory()->pendingApproval()->create([
        'name' => 'Pending User',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::users.index')
        ->call('editUser', $pendingUser->id)
        ->set('form.is_approved', true)
        ->call('saveUser')
        ->assertHasNoErrors();

    $pendingUser->refresh();

    expect($pendingUser->is_approved)->toBeTrue()
        ->and($pendingUser->approved_by)->toBe($admin->id);
});
