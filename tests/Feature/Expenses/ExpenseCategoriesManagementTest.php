<?php

use App\Models\Expense;
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

test('user can delete an unused custom expense category from expenses page', function () {
    $user = User::factory()->create();
    $category = ExpenseCategory::factory()->create([
        'name' => 'Bridge Tolls',
        'is_system' => false,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('deleteCategory', $category->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('expense_categories', [
        'id' => $category->id,
    ]);
});

test('user cannot delete an expense category while it is in use', function () {
    $user = User::factory()->create();
    $car = $user->cars()->create([
        'make' => 'Ford',
        'model' => 'Focus',
        'year' => 2019,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create([
        'name' => 'Bridge Tolls',
        'is_system' => false,
    ]);

    Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $category->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('deleteCategory', $category->id)
        ->assertHasErrors(['categoryName']);

    $this->assertDatabaseHas('expense_categories', [
        'id' => $category->id,
    ]);
});
