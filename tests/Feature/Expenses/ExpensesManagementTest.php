<?php

use App\Models\Car;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MileageLog;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->assertSee('Expenses')
        ->assertSee('Tap any expense to edit it.');
});

test('user can create an expense', function () {
    Storage::fake('local');

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
        ->set('newAttachments', [UploadedFile::fake()->image('receipt.jpg')])
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
    expect($expense->attachments)->toHaveCount(1);

    Storage::disk('local')->assertExists($expense->attachments->first()->path);
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

test('expenses page shows docs attached hint for rows with attachments', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $category = ExpenseCategory::factory()->create(['name' => 'Parking']);
    $expense = Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $category->id,
        'expense_date' => now()->toDateString(),
    ]);

    Storage::disk('local')->put('attachments/test/receipt.pdf', 'pdf');
    $expense->attachments()->create([
        'user_id' => $user->id,
        'disk' => 'local',
        'path' => 'attachments/test/receipt.pdf',
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertOk()
        ->assertSee('Docs attached');
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

test('new expense defaults odometer from latest known car reading', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 12000,
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create();

    MileageLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'log_date' => now()->subDay()->toDateString(),
        'start_odometer' => 12100,
        'end_odometer' => 12345,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('startCreating')
        ->assertSet('form.car_id', (string) $car->id)
        ->assertSet('form.odometer', '12345')
        ->set('form.expense_category_id', (string) $category->id);
});

test('changing car while creating expense refreshes the default odometer', function () {
    $user = User::factory()->create();
    $firstCar = Car::factory()->for($user)->create([
        'current_odometer' => 15000,
        'is_default' => true,
    ]);
    $secondCar = Car::factory()->for($user)->create([
        'current_odometer' => 22000,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::expenses.index')
        ->call('startCreating')
        ->assertSet('form.car_id', (string) $firstCar->id)
        ->assertSet('form.odometer', '15000')
        ->set('form.car_id', (string) $secondCar->id)
        ->assertSet('form.odometer', '22000');
});
