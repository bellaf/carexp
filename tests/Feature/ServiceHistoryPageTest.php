<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\Reimbursement;
use App\Models\User;
use App\Models\VehicleObligation;

test('guests are redirected to login from history page', function () {
    $this->get(route('history.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view merged vehicle history', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create([
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Corolla',
        'is_default' => true,
    ]);

    $expenseCategory = ExpenseCategory::factory()->create(['name' => 'Parking']);
    $fuelAccount = Account::factory()->create(['key' => 'fuel_expense', 'name' => 'Fuel', 'group' => 'expense', 'is_system' => true]);
    $maintenanceAccount = Account::factory()->create(['key' => 'maintenance_expense', 'name' => 'Maintenance', 'group' => 'expense', 'is_system' => true]);
    $incomeAccount = Account::factory()->create(['key' => 'company_car_allowance_income', 'name' => 'Allowance', 'group' => 'income', 'is_system' => true]);

    $fuelLedger = $user->ledgerEntries()->create([
        'car_id' => $car->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => '2026-02-10',
        'entry_type' => 'expense',
        'amount' => 50,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    FuelLog::factory()->for($car)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $fuelLedger->id,
        'log_date' => '2026-02-10',
        'volume' => 34.2,
        'volume_unit' => 'liters',
        'full_tank' => true,
    ]);

    Expense::factory()->for($car)->create([
        'user_id' => $user->id,
        'expense_category_id' => $expenseCategory->id,
        'amount' => 12.5,
        'expense_date' => '2026-02-12',
        'vendor' => 'Town Centre',
    ]);

    MaintenanceRecord::factory()->for($car)->create([
        'user_id' => $user->id,
        'service_type' => 'oil_change',
        'service_date' => '2026-02-13',
        'provider' => 'Garage One',
        'ledger_entry_id' => $user->ledgerEntries()->create([
            'car_id' => $car->id,
            'account_id' => $maintenanceAccount->id,
            'entry_date' => '2026-02-13',
            'entry_type' => 'expense',
            'amount' => 90,
            'source_type' => 'maintenance_record',
            'source_id' => 1,
        ])->id,
    ]);

    VehicleObligation::factory()->for($car)->create([
        'user_id' => $user->id,
        'obligation_type' => 'insurance',
        'provider' => 'Admiral',
        'due_date' => '2026-02-14',
        'amount' => 420,
    ]);

    Reimbursement::factory()->for($car)->create([
        'user_id' => $user->id,
        'source' => 'Employer',
        'reference' => 'FEB-2026',
        'reimbursed_date' => '2026-02-15',
        'ledger_entry_id' => $user->ledgerEntries()->create([
            'car_id' => $car->id,
            'account_id' => $incomeAccount->id,
            'entry_date' => '2026-02-15',
            'entry_type' => 'income',
            'amount' => 75,
            'source_type' => 'reimbursement',
            'source_id' => 1,
        ])->id,
    ]);

    $this->actingAs($user)
        ->get(route('history.index'))
        ->assertOk()
        ->assertSee('History')
        ->assertSee('Fuel Fill-Up')
        ->assertSee('Parking')
        ->assertSee('Oil Change')
        ->assertSee('Insurance')
        ->assertSee('Employer');
});

test('history can be filtered by event type and car', function () {
    $user = User::factory()->create();
    $carA = Car::factory()->for($user)->create([
        'year' => 2020,
        'make' => 'Ford',
        'model' => 'Focus',
        'is_default' => true,
    ]);
    $carB = Car::factory()->for($user)->create([
        'year' => 2021,
        'make' => 'Honda',
        'model' => 'Civic',
    ]);

    $fuelAccount = Account::factory()->create(['key' => 'fuel_expense', 'name' => 'Fuel', 'group' => 'expense', 'is_system' => true]);

    $ledgerA = $user->ledgerEntries()->create([
        'car_id' => $carA->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => '2026-02-10',
        'entry_type' => 'expense',
        'amount' => 48,
        'source_type' => 'fuel_log',
        'source_id' => 1,
    ]);

    $ledgerB = $user->ledgerEntries()->create([
        'car_id' => $carB->id,
        'account_id' => $fuelAccount->id,
        'entry_date' => '2026-02-11',
        'entry_type' => 'expense',
        'amount' => 52,
        'source_type' => 'fuel_log',
        'source_id' => 2,
    ]);

    FuelLog::factory()->for($carA)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $ledgerA->id,
        'log_date' => '2026-02-10',
        'full_tank' => true,
    ]);

    FuelLog::factory()->for($carB)->create([
        'user_id' => $user->id,
        'ledger_entry_id' => $ledgerB->id,
        'log_date' => '2026-02-11',
        'full_tank' => false,
    ]);

    MaintenanceRecord::factory()->for($carA)->create([
        'user_id' => $user->id,
        'service_type' => 'Brake Service',
        'service_date' => '2026-02-09',
    ]);

    $this->actingAs($user)
        ->get(route('history.index', ['car_id' => $carB->id, 'type' => 'fuel']))
        ->assertOk()
        ->assertSee('Partial Fuel Fill')
        ->assertDontSee('Fuel Fill-Up')
        ->assertDontSee('Brake Service');
});
