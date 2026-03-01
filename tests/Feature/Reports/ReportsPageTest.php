<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\VehicleObligation;

test('guests are redirected to login from reports page', function () {
    $this->get(route('reports.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view summary report totals', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

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
        'amount' => 150,
        'entry_date' => now()->startOfYear()->addDays(1)->toDateString(),
        'source_type' => 'fuel_log',
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $incomeAccount->id,
        'entry_type' => 'income',
        'amount' => 50,
        'entry_date' => now()->startOfYear()->addDays(2)->toDateString(),
        'source_type' => 'reimbursement',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'summary', 'period' => 'year_to_date']))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Calendar Year')
        ->assertSee('Monthly Trend')
        ->assertSee('150.00')
        ->assertSee('50.00')
        ->assertSee('100.00');
});

test('category report groups ledger entries by account', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    $parkingAccount = Account::factory()->create([
        'key' => 'parking_expense',
        'name' => 'Parking',
        'group' => 'expense',
        'is_system' => true,
    ]);

    LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $parkingAccount->id,
        'entry_type' => 'expense',
        'amount' => 12.5,
        'entry_date' => now()->startOfMonth()->addDay()->toDateString(),
        'source_type' => 'expense',
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'category', 'period' => 'this_month']))
        ->assertOk()
        ->assertSee('Category Breakdown')
        ->assertSee('Parking')
        ->assertSee('12.50');
});

test('fuel report shows fuel metrics from fuel logs', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'liters',
    ]);
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $ledgerEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 45,
        'entry_date' => now()->startOfMonth()->addDay()->toDateString(),
        'source_type' => 'fuel_log',
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $ledgerEntry->id,
        'log_date' => now()->startOfMonth()->addDay()->toDateString(),
        'volume' => 30,
        'volume_unit' => 'liters',
        'price_per_unit' => 1.5,
        'full_tank' => true,
        'calculated_efficiency' => 33.3,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'fuel', 'period' => 'this_month']))
        ->assertOk()
        ->assertSee('Fuel Trend')
        ->assertSee('45.00')
        ->assertSee('30.000')
        ->assertSee('33.300');
});

test('fuel report shows weighted average efficiency instead of arithmetic mean', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
        'volume_unit' => 'gallons',
    ]);
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $firstEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 30,
        'entry_date' => '2026-02-01',
        'source_type' => 'fuel_log',
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $firstEntry->id,
        'log_date' => '2026-02-01',
        'odometer' => 10000,
        'volume' => 8,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => null,
    ]);

    $secondEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 20,
        'entry_date' => '2026-02-02',
        'source_type' => 'fuel_log',
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $secondEntry->id,
        'log_date' => '2026-02-02',
        'odometer' => 10100,
        'volume' => 5,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 20,
    ]);

    $thirdEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 40,
        'entry_date' => '2026-02-03',
        'source_type' => 'fuel_log',
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $thirdEntry->id,
        'log_date' => '2026-02-03',
        'odometer' => 10600,
        'volume' => 10,
        'volume_unit' => 'gallons',
        'full_tank' => true,
        'calculated_efficiency' => 50,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'fuel', 'period' => 'all_time']))
        ->assertOk()
        ->assertSee('40.000')
        ->assertDontSee('35.000');
});

test('obligations report shows due items by period', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();

    VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'insurance',
        'provider' => 'Admiral',
        'due_date' => now()->startOfMonth()->addDays(5)->toDateString(),
        'amount' => 420,
    ]);

    VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'tax',
        'provider' => 'DVLA',
        'due_date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString(),
        'amount' => 190,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'obligations', 'period' => 'this_month']))
        ->assertOk()
        ->assertSee('Obligation Schedule')
        ->assertSee('Insurance')
        ->assertSee('Admiral')
        ->assertDontSee('DVLA');
});

test('ownership report shows all time cost per distance metrics by car', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $car = Car::factory()->for($user)->create([
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Corolla',
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

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 150,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $maintenanceAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 50,
        'source_type' => 'maintenance_record',
        'source_id' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'ownership']))
        ->assertOk()
        ->assertSee('Ownership Metrics')
        ->assertSee('Toyota Corolla')
        ->assertSee('2,000 mi')
        ->assertSee('$0.10/mi')
        ->assertSee('$0.08/mi')
        ->assertSee('$0.03/mi');
});

test('ownership report shows sold vehicle closure metrics', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $car = Car::factory()->for($user)->create([
        'year' => 2019,
        'make' => 'Honda',
        'model' => 'Civic',
        'purchase_odometer' => 30000,
        'current_odometer' => 60000,
        'purchase_price' => 12000,
        'sale_date' => '2026-02-28',
        'sale_price' => 7000,
        'sale_odometer' => 59000,
    ]);

    $fuelAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => now()->toDateString(),
        'entry_type' => 'expense',
        'amount' => 590,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'ownership']))
        ->assertOk()
        ->assertSee('Honda Civic')
        ->assertSee('Sold')
        ->assertSee('29,000 mi')
        ->assertSee('$12,000.00')
        ->assertSee('$7,000.00')
        ->assertSee('$5,590.00')
        ->assertSee('$0.19/mi');
});

test('ownership report falls back to recorded odometer history when purchase odometer is missing', function () {
    $user = User::factory()->create([
        'measurement_system' => 'imperial',
    ]);
    $car = Car::factory()->for($user)->create([
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Corolla',
        'purchase_odometer' => null,
        'current_odometer' => 12000,
    ]);

    $fuelAccount = Account::factory()->create([
        'key' => 'fuel_expense',
        'name' => 'Fuel',
        'group' => 'expense',
        'is_system' => true,
    ]);

    $firstLedgerEntry = LedgerEntry::factory()->for($car)->create([
        'user_id' => $user->id,
        'account_id' => $fuelAccount->id,
        'entry_type' => 'expense',
        'amount' => 120,
        'entry_date' => now()->subMonths(2)->toDateString(),
        'source_type' => 'fuel_log',
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $firstLedgerEntry->id,
        'log_date' => now()->subMonths(2)->toDateString(),
        'odometer' => 10000,
        'volume' => 20,
        'volume_unit' => 'liters',
        'price_per_unit' => 1.2,
        'full_tank' => true,
    ]);

    $this->actingAs($user)
        ->get(route('reports.index', ['report' => 'ownership']))
        ->assertOk()
        ->assertSee('2,000 mi')
        ->assertSee('$0.06/mi');
});
