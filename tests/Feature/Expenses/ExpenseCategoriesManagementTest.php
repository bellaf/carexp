<?php

use App\Models\ExpenseCategory;
use App\Models\User;
use Livewire\Livewire;

test('user can create a custom expense category from expenses page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('startCreatingCategory')
        ->set('categoryName', 'MOT & Servicing')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('expense_categories', [
        'name' => 'MOT & Servicing',
        'is_system' => false,
    ]);
});

test('user can rename an existing expense category from expenses page', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create([
        'name' => 'Registration/DMV',
        'is_system' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('editCategory', $category->id)
        ->set('categoryName', 'Road Tax / Registration')
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect($category->fresh()->name)->toBe('Road Tax / Registration');
});
