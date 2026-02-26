<?php

use App\Models\Car;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Livewire\Livewire;

test('guests are redirected to login from expenses page', function () {
    $this->get(route('expenses.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view expenses page', function () {
    $user = User::factory()->create();
    Car::factory()->for($user)->create();
    $this->seed(ExpenseCategorySeeder::class);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Expenses');
});

test('user can create an expense', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('startCreating')
        ->set('form.car_id', (string) $car->id)
        ->set('form.expense_category_id', (string) $category->id)
        ->set('form.amount', '49.99')
        ->set('form.expense_date', now()->format('Y-m-d'))
        ->set('form.vendor', 'Shell')
        ->set('form.tags', 'personal, fuel')
        ->call('saveExpense')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('expenses', [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'amount' => '49.99',
        'vendor' => 'Shell',
    ]);

    $expense = Expense::query()
        ->where('user_id', $user->id)
        ->where('car_id', $car->id)
        ->where('amount', '49.99')
        ->firstOrFail();

    expect($expense->ledger_entry_id)->not->toBeNull();
    $this->assertDatabaseHas('ledger_entries', [
        'id' => $expense->ledger_entry_id,
        'user_id' => $user->id,
        'entry_type' => 'expense',
        'source_type' => 'expense',
        'source_id' => $expense->id,
    ]);
});

test('user can filter expenses by category', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $fuel = ExpenseCategory::factory()->create(['name' => 'Fuel']);
    $insurance = ExpenseCategory::factory()->create(['name' => 'Insurance']);

    Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $fuel->id,
        'vendor' => 'Fuel Station',
    ]);

    Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $insurance->id,
        'vendor' => 'Insurance Co',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->set('filterPeriod', 'all_time')
        ->set('filterCategoryId', (string) $fuel->id)
        ->assertSee('Fuel Station')
        ->assertDontSee('Insurance Co');
});

test('user can update and delete their expense', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $category = ExpenseCategory::factory()->create();
    $expense = Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $category->id,
        'vendor' => 'Old Vendor',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('editExpense', $expense->id)
        ->set('form.vendor', 'New Vendor')
        ->call('saveExpense')
        ->assertHasNoErrors()
        ->call('deleteExpense', $expense->id);

    $this->assertDatabaseMissing('expenses', [
        'id' => $expense->id,
    ]);
    $this->assertDatabaseMissing('ledger_entries', [
        'source_type' => 'expense',
        'source_id' => $expense->id,
    ]);
});
