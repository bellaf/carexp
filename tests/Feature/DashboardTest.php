<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\MaintenanceRecord;
use App\Models\QuickAction;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Models\VehicleObligation;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows running totals from ledger entries', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $car->update(['is_default' => true]);

    $expenseAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $incomeAccount = Account::factory()->create([
        'key' => 'company_car_allowance_income',
        'name' => 'Company Car Allowance',
        'group' => 'income',
        'is_system' => true,
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $expenseAccount->id,
        'entry_type' => 'expense',
        'amount' => 200,
        'entry_date' => now()->toDateString(),
        'source_type' => 'fuel_log',
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $incomeAccount->id,
        'entry_type' => 'income',
        'amount' => 80,
        'entry_date' => now()->toDateString(),
        'source_type' => 'reimbursement',
    ]);

    QuickAction::factory()->for($user)->for($car)->create([
        'name' => 'Fuel Up',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Net Cost (All-Time)')
        ->assertSee('Financial Summary')
        ->assertSee('Current Car')
        ->assertSee('Tap once to capture a common entry quickly.')
        ->assertSee($car->make)
        ->assertSee('Transactions')
        ->assertSee('200.00')
        ->assertSee('80.00')
        ->assertSee('120.00');
});

test('dashboard shows current car ownership cost metrics', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
        'purchase_odometer' => 10000,
        'current_odometer' => 12000,
    ]);

    $fuelAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $maintenanceAccount = Account::factory()->create([
        'key' => 'maintenance_expense',
        'name' => 'Maintenance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $incomeAccount = Account::factory()->create([
        'key' => 'company_car_allowance_income',
        'name' => 'Company Car Allowance',
        'group' => 'income',
        'is_system' => true,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 200,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $maintenanceAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 100,
        'source_type' => 'maintenance_record',
        'source_id' => 1,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $incomeAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'income',
        'amount' => 50,
        'source_type' => 'reimbursement',
        'source_id' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Current Car Ownership Metrics')
        ->assertSee('2,000 mi')
        ->assertSee('$0.13/mi')
        ->assertSee('$0.10/mi')
        ->assertSee('$0.05/mi')
        ->assertSee('Total Ownership Cost / mi');
});

test('dashboard ownership metrics fall back to recorded odometer history when purchase odometer is missing', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
        'purchase_odometer' => null,
        'current_odometer' => 12000,
    ]);

    $fuelAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $ledgerEntry = $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => now()->subMonths(2)->toDateString(),
        'entry_type' => 'expense',
        'amount' => 120,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    \App\Models\FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $ledgerEntry->id,
        'log_date' => now()->subMonths(2)->toDateString(),
        'odometer' => 10000,
        'volume' => 20,
        'volume_unit' => 'liters',
        'price_per_unit' => 1.2,
        'full_tank' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Current Car Ownership Metrics')
        ->assertSee('2,000 mi')
        ->assertSee('$0.06/mi');
});

test('dashboard transaction type filter narrows table rows', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $fuelAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $maintenanceAccount = Account::factory()->create([
        'key' => 'maintenance_expense',
        'name' => 'Maintenance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $fuelAccount->id,
        'entry_type' => 'expense',
        'amount' => 45,
        'entry_date' => now()->toDateString(),
        'source_type' => 'fuel_log',
        'reference' => 'Fuel Station Fill',
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $maintenanceAccount->id,
        'entry_type' => 'expense',
        'amount' => 95,
        'entry_date' => now()->toDateString(),
        'source_type' => 'maintenance_record',
        'reference' => 'Brake Service Work',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard', ['transaction_type' => 'fuel_log']))
        ->assertOk()
        ->assertSee('Fuel Station Fill')
        ->assertDontSee('Brake Service Work');
});

test('dashboard period filter narrows table rows', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'other_expense',
        'name' => 'Other Expense',
        'group' => 'expense',
        'is_system' => true,
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 30,
        'entry_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString(),
        'source_type' => 'expense',
        'reference' => 'Last Month Parking',
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 20,
        'entry_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'source_type' => 'expense',
        'reference' => 'This Month Parking',
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard', ['period' => 'last_month']))
        ->assertOk()
        ->assertSee('Last Month Parking')
        ->assertDontSee('This Month Parking');
});

test('dashboard shows actual ytd and projected remaining totals', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $car->update(['is_default' => true]);

    $expenseAccount = Account::factory()->create([
        'key' => 'insurance_expense',
        'name' => 'Insurance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $incomeAccount = Account::factory()->create([
        'key' => 'company_car_allowance_income',
        'name' => 'Company Car Allowance',
        'group' => 'income',
        'is_system' => true,
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $expenseAccount->id,
        'entry_type' => 'expense',
        'amount' => 200,
        'entry_date' => now()->subDays(5)->toDateString(),
        'source_type' => 'expense',
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $incomeAccount->id,
        'entry_type' => 'income',
        'amount' => 80,
        'entry_date' => now()->subDays(4)->toDateString(),
        'source_type' => 'reimbursement',
    ]);

    DB::table('recurring_transactions')->insert([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $expenseAccount->id,
        'entry_type' => 'expense',
        'amount' => 300,
        'cadence' => 'yearly',
        'next_entry_date' => now()->addDay()->toDateString(),
        'end_date' => null,
        'reference' => 'Annual Policy',
        'notes' => null,
        'is_active' => true,
        'last_generated_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('recurring_transactions')->insert([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $incomeAccount->id,
        'entry_type' => 'income',
        'amount' => 100,
        'cadence' => 'yearly',
        'next_entry_date' => now()->addDay()->toDateString(),
        'end_date' => null,
        'reference' => 'Allowance',
        'notes' => null,
        'is_active' => true,
        'last_generated_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Actual YTD')
        ->assertSee('Projected Remaining')
        ->assertSee('Projected Year-End Net Cost')
        ->assertSee('200.00')
        ->assertSee('80.00')
        ->assertSee('300.00')
        ->assertSee('100.00')
        ->assertSee('320.00');
});

test('dashboard shows upcoming service and recurring indicators for next 14 days', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'insurance_expense',
        'name' => 'Insurance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'service_type' => 'oil_change',
        'next_due_date' => now()->addDays(7)->toDateString(),
    ]);

    DB::table('recurring_transactions')->insert([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 99.00,
        'cadence' => 'monthly',
        'next_entry_date' => now()->addDays(5)->toDateString(),
        'end_date' => null,
        'reference' => 'Policy',
        'notes' => null,
        'is_active' => true,
        'last_generated_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Service Due (Next 14 Days)')
        ->assertSee('Recurring Due (Next 14 Days)')
        ->assertSee('oil_change')
        ->assertSee('Insurance');
});

test('dashboard shows upcoming obligations due within 30 days', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'insurance',
        'provider' => 'Admiral',
        'due_date' => now()->addDays(10)->toDateString(),
        'is_active' => true,
    ]);

    VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'tax',
        'provider' => 'DVLA',
        'due_date' => now()->addDays(45)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Delete Ledger Entry')
        ->assertSee('Obligations Due (Next 30 Days)')
        ->assertSee('Insurance')
        ->assertDontSee('Tax / Registration');
});

test('dashboard ledger delete removes obligation financial entry and clears obligation link', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'insurance_expense',
        'name' => 'Insurance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $obligation = VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'insurance',
        'amount' => 420.50,
    ]);

    $ledgerEntry = $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 420.50,
        'source_type' => 'vehicle_obligation',
        'source_id' => $obligation->id,
    ]);

    $obligation->update(['ledger_entry_id' => $ledgerEntry->id]);

    $this->actingAs($user)
        ->delete(route('dashboard.ledger.delete', $ledgerEntry))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('ledger_entries', ['id' => $ledgerEntry->id]);
    $this->assertDatabaseHas('vehicle_obligations', [
        'id' => $obligation->id,
        'ledger_entry_id' => null,
        'amount' => null,
    ]);
});

test('service due indicator is based on maintenance due dates, not recurring schedules', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'maintenance_expense',
        'name' => 'Maintenance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    DB::table('recurring_transactions')->insert([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 50.00,
        'cadence' => 'monthly',
        'next_entry_date' => now()->addDays(3)->toDateString(),
        'end_date' => null,
        'reference' => 'Recurring Service Plan',
        'notes' => null,
        'is_active' => true,
        'last_generated_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Service Due (Next 14 Days)')
        ->assertSee('No service due in the next 14 days.')
        ->assertSee('Recurring Due (Next 14 Days)')
        ->assertDontSee('No recurring transactions due in the next 14 days.')
        ->assertSee('Maintenance');
});

test('service due indicator includes odometer-based due soon reminders', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 49550,
    ]);

    MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'service_type' => 'timing_belt',
        'next_due_date' => now()->addMonths(3)->toDateString(),
        'next_due_odometer' => 50000,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Service Due (Next 14 Days)')
        ->assertSee('timing_belt')
        ->assertSee('Odometer')
        ->assertSee('49,550/50,000');
});

test('dashboard maintenance and recurring modal actions can update and delete records', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'current_odometer' => 10000,
    ]);
    $account = Account::factory()->create([
        'key' => 'insurance_expense',
        'name' => 'Insurance',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $maintenance = MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'next_due_date' => now()->addDays(4)->toDateString(),
        'next_due_odometer' => 11000,
        'notes' => 'old note',
    ]);

    $recurring = RecurringTransaction::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 55.00,
        'cadence' => 'monthly',
        'next_entry_date' => now()->addDays(3)->toDateString(),
        'reference' => 'old ref',
        'notes' => 'old recurring note',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->put(route('dashboard.maintenance.update', $maintenance), [
        'next_due_date' => now()->addDays(10)->toDateString(),
        'next_due_odometer' => 11200,
        'notes' => 'updated note',
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('maintenance_records', [
        'id' => $maintenance->id,
        'next_due_odometer' => 11200,
        'notes' => 'updated note',
    ]);

    $this->put(route('dashboard.recurring.update', $recurring), [
        'next_entry_date' => now()->addDays(8)->toDateString(),
        'amount' => 88.50,
        'cadence' => 'quarterly',
        'end_date' => '',
        'reference' => 'new ref',
        'notes' => 'updated recurring note',
        'is_active' => 0,
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurring->id,
        'amount' => '88.50',
        'cadence' => 'quarterly',
        'reference' => 'new ref',
        'notes' => 'updated recurring note',
        'is_active' => 0,
    ]);

    $this->delete(route('dashboard.maintenance.delete', $maintenance))
        ->assertRedirect(route('dashboard'));
    $this->delete(route('dashboard.recurring.delete', $recurring))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('maintenance_records', ['id' => $maintenance->id]);
    $this->assertDatabaseMissing('recurring_transactions', ['id' => $recurring->id]);
});

test('dashboard quick actions include confirmation modal content', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'is_default' => true,
    ]);
    $category = ExpenseCategory::factory()->create([
        'key' => 'tolls',
        'name' => 'Tolls',
    ]);

    QuickAction::factory()->for($user)->create([
        'car_id' => $car->id,
        'expense_category_id' => $category->id,
        'name' => 'Dartford Toll Single',
        'amount' => 2.50,
        'vendor' => 'Dartford Crossing',
        'notes' => 'Northbound only',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Quick Actions')
        ->assertSee('Dartford Toll Single')
        ->assertSee('Run Quick Action')
        ->assertSeeText('Confirm & Post');
});
