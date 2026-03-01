<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;

test('new users receive default car tracking preferences', function () {
    $user = User::factory()->create()->fresh();

    expect($user->preferred_currency)->toBe('GBP')
        ->and($user->measurement_system)->toBe('imperial')
        ->and($user->volume_unit)->toBe('gallons')
        ->and($user->timezone)->toBe('UTC');
});

test('default expense categories are seeded', function () {
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::query()->count())->toBe(13)
        ->and(
            ExpenseCategory::query()
                ->where('key', 'fuel')
                ->where('name', 'Fuel')
                ->where('is_system', true)
                ->exists()
        )->toBeTrue();
});

test('expense factory keeps user and car ownership aligned', function () {
    $expense = Expense::factory()->create();

    expect($expense->car->user_id)->toBe($expense->user_id);
});

test('fuel and maintenance factories keep user and car ownership aligned', function () {
    $fuelLog = FuelLog::factory()->create();
    $maintenanceRecord = MaintenanceRecord::factory()->create();

    expect($fuelLog->car->user_id)->toBe($fuelLog->user_id)
        ->and($maintenanceRecord->car->user_id)->toBe($maintenanceRecord->user_id);
});
