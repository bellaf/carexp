<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\User;

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
