<?php

use App\Models\Account;
use App\Models\Car;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('command generates due recurring ledger entries and advances next date', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'group' => 'expense',
    ]);

    $recurringId = DB::table('recurring_transactions')->insertGetId([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_type' => 'expense',
        'amount' => 100.00,
        'cadence' => 'monthly',
        'next_entry_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => null,
        'reference' => 'Insurance',
        'notes' => 'Monthly policy installment',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('app:generate-recurring-transactions')
        ->assertSuccessful();

    $this->assertDatabaseHas('ledger_entries', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'recurring_transaction_id' => $recurringId,
        'entry_type' => 'expense',
        'amount' => '100.00',
        'source_type' => 'recurring',
    ]);

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurringId,
        'next_entry_date' => now()->startOfMonth()->addMonthNoOverflow()->format('Y-m-d'),
    ]);
});

test('command generates multiple missed recurring entries up to run date', function () {
    $user = User::factory()->create();
    $car = Car::factory()->for($user)->create();
    $account = Account::factory()->create([
        'group' => 'income',
    ]);

    $startDate = now()->startOfMonth()->subMonthsNoOverflow(2);
    $runDate = now()->startOfMonth();

    $recurringId = DB::table('recurring_transactions')->insertGetId([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'account_id' => $account->id,
        'entry_type' => 'income',
        'amount' => 55.00,
        'cadence' => 'monthly',
        'next_entry_date' => $startDate->format('Y-m-d'),
        'end_date' => null,
        'reference' => 'Allowance',
        'notes' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('app:generate-recurring-transactions', [
        '--date' => $runDate->format('Y-m-d'),
    ])->assertSuccessful();

    expect(DB::table('ledger_entries')->where('recurring_transaction_id', $recurringId)->count())->toBe(3);

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurringId,
        'next_entry_date' => $runDate->addMonthNoOverflow()->format('Y-m-d'),
    ]);
});
