<?php

use App\Http\Controllers\ReportsController;
use App\Models\Account;
use App\Models\Expense;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\QuickAction;
use App\Models\RecurringTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function (Request $request) {
    $user = $request->user();
    $monthStart = now()->startOfMonth();
    $currentCar = $user->cars()
        ->where('is_archived', false)
        ->orderByDesc('is_default')
        ->orderBy('make')
        ->orderBy('model')
        ->first();

    $transactionTypeOptions = [
        'all' => 'All Transactions',
        'fuel_log' => 'Fuel',
        'maintenance_record' => 'Maintenance',
        'expense' => 'Manual Expense',
        'reimbursement' => 'Reimbursement',
        'recurring' => 'Recurring',
    ];
    $periodOptions = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'year_to_date' => 'Year to Date',
        'all_time' => 'All Time',
    ];

    $selectedTransactionType = $request->string('transaction_type')->toString();
    $selectedPeriod = $request->string('period')->toString();

    if (! array_key_exists($selectedTransactionType, $transactionTypeOptions)) {
        $selectedTransactionType = 'all';
    }
    if (! array_key_exists($selectedPeriod, $periodOptions)) {
        $selectedPeriod = 'this_month';
    }

    $periodStartDate = match ($selectedPeriod) {
        'last_month' => now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
        'year_to_date' => now()->startOfYear()->format('Y-m-d'),
        'all_time' => null,
        default => now()->startOfMonth()->format('Y-m-d'),
    };

    $periodEndDate = match ($selectedPeriod) {
        'last_month' => now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
        default => null,
    };

    $ledgerEntries = $user->ledgerEntries()->with(['car', 'account']);

    $allTimeExpenses = (float) (clone $ledgerEntries)
        ->where('entry_type', 'expense')
        ->sum('amount');

    $allTimeReimbursements = (float) (clone $ledgerEntries)
        ->where('entry_type', 'income')
        ->sum('amount');

    $monthExpenses = (float) (clone $ledgerEntries)
        ->where('entry_type', 'expense')
        ->whereDate('entry_date', '>=', $monthStart)
        ->sum('amount');

    $monthReimbursements = (float) (clone $ledgerEntries)
        ->where('entry_type', 'income')
        ->whereDate('entry_date', '>=', $monthStart)
        ->sum('amount');

    $yearStart = now()->startOfYear();
    $yearEnd = now()->endOfYear();
    $forecastStart = now()->startOfDay();

    $actualYearExpenses = (float) (clone $ledgerEntries)
        ->where('entry_type', 'expense')
        ->whereDate('entry_date', '>=', $yearStart)
        ->whereDate('entry_date', '<=', $forecastStart)
        ->sum('amount');
    $actualYearReimbursements = (float) (clone $ledgerEntries)
        ->where('entry_type', 'income')
        ->whereDate('entry_date', '>=', $yearStart)
        ->whereDate('entry_date', '<=', $forecastStart)
        ->sum('amount');

    $recurringSchedules = DB::table('recurring_transactions')
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->whereDate('next_entry_date', '<=', $yearEnd->format('Y-m-d'))
        ->where(function ($query) use ($forecastStart): void {
            $query->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $forecastStart->format('Y-m-d'));
        })
        ->get();

    $recurringForecastExpense = 0.0;
    $recurringForecastReimbursement = 0.0;

    $nextDate = function (CarbonImmutable $date, string $cadence): CarbonImmutable {
        return match ($cadence) {
            'monthly' => $date->addMonthNoOverflow(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'yearly' => $date->addYearNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    };

    foreach ($recurringSchedules as $schedule) {
        $currentDate = CarbonImmutable::parse($schedule->next_entry_date);
        $endDate = $schedule->end_date !== null ? CarbonImmutable::parse($schedule->end_date) : null;

        while ($currentDate->lt($forecastStart)) {
            $currentDate = $nextDate($currentDate, $schedule->cadence);
        }

        while ($currentDate->lte($yearEnd) && ($endDate === null || $currentDate->lte($endDate))) {
            if ($schedule->entry_type === 'expense') {
                $recurringForecastExpense += (float) $schedule->amount;
            } else {
                $recurringForecastReimbursement += (float) $schedule->amount;
            }

            $currentDate = $nextDate($currentDate, $schedule->cadence);
        }
    }

    $actualYearNetCost = $actualYearExpenses - $actualYearReimbursements;
    $projectedRemainingNetCost = $recurringForecastExpense - $recurringForecastReimbursement;
    $projectedYearExpenses = $actualYearExpenses + $recurringForecastExpense;
    $projectedYearReimbursements = $actualYearReimbursements + $recurringForecastReimbursement;
    $projectedYearNetCost = $actualYearNetCost + $projectedRemainingNetCost;

    $upcomingWindowStart = now()->startOfDay();
    $upcomingWindowEnd = now()->addDays(14)->endOfDay();

    $isMaintenanceDueSoon = static function ($record) use ($upcomingWindowStart, $upcomingWindowEnd): bool {
        $isDateDueSoon = $record->next_due_date !== null
            && $record->next_due_date->gte($upcomingWindowStart)
            && $record->next_due_date->lte($upcomingWindowEnd);

        $currentOdometer = $record->car?->current_odometer;
        $isOdometerDueSoon = $record->next_due_odometer !== null
            && $currentOdometer !== null
            && $currentOdometer >= ((int) $record->next_due_odometer - 500);

        return $isDateDueSoon || $isOdometerDueSoon;
    };

    $upcomingMaintenanceAll = $user->maintenanceRecords()
        ->with('car')
        ->where(function ($query): void {
            $query->whereNotNull('next_due_date')
                ->orWhereNotNull('next_due_odometer');
        })
        ->orderBy('next_due_date')
        ->orderBy('next_due_odometer')
        ->get()
        ->filter($isMaintenanceDueSoon)
        ->values();

    $upcomingMaintenance = $upcomingMaintenanceAll
        ->sortBy(function ($record): array {
            $datePriority = $record->next_due_date?->timestamp ?? PHP_INT_MAX;
            $currentOdometer = (int) ($record->car?->current_odometer ?? 0);
            $odometerPriority = $record->next_due_odometer !== null
                ? abs((int) $record->next_due_odometer - $currentOdometer)
                : PHP_INT_MAX;

            return [$datePriority, $odometerPriority];
        })
        ->take(5)
        ->values();

    $upcomingRecurringQuery = $user->recurringTransactions()
        ->where('is_active', true)
        ->whereDate('next_entry_date', '>=', $upcomingWindowStart)
        ->whereDate('next_entry_date', '<=', $upcomingWindowEnd);

    $upcomingRecurringTransactions = (clone $upcomingRecurringQuery)
        ->with(['account', 'car'])
        ->orderBy('next_entry_date')
        ->limit(5)
        ->get();

    $quickActions = $user->quickActions()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->limit(4)
        ->get();

    $transactions = (clone $ledgerEntries)
        ->when($selectedTransactionType !== 'all', fn ($query) => $query->where('source_type', $selectedTransactionType))
        ->when($periodStartDate !== null, fn ($query) => $query->whereDate('entry_date', '>=', $periodStartDate))
        ->when($periodEndDate !== null, fn ($query) => $query->whereDate('entry_date', '<=', $periodEndDate))
        ->orderByDesc('entry_date')
        ->orderByDesc('id')
        ->paginate(50)
        ->withQueryString();

    return view('dashboard', [
        'currencyCode' => $user->preferred_currency,
        'currentCar' => $currentCar,
        'allTimeExpenses' => $allTimeExpenses,
        'allTimeReimbursements' => $allTimeReimbursements,
        'allTimeNetCost' => $allTimeExpenses - $allTimeReimbursements,
        'monthNetCost' => $monthExpenses - $monthReimbursements,
        'actualYearExpenses' => $actualYearExpenses,
        'actualYearReimbursements' => $actualYearReimbursements,
        'actualYearNetCost' => $actualYearNetCost,
        'projectedRemainingExpenses' => $recurringForecastExpense,
        'projectedRemainingReimbursements' => $recurringForecastReimbursement,
        'projectedRemainingNetCost' => $projectedRemainingNetCost,
        'projectedYearExpenses' => $projectedYearExpenses,
        'projectedYearReimbursements' => $projectedYearReimbursements,
        'projectedYearNetCost' => $projectedYearNetCost,
        'upcomingMaintenanceCount' => (int) $upcomingMaintenanceAll->count(),
        'upcomingMaintenance' => $upcomingMaintenance,
        'upcomingRecurringCount' => (int) (clone $upcomingRecurringQuery)->count(),
        'upcomingRecurringTransactions' => $upcomingRecurringTransactions,
        'quickActions' => $quickActions,
        'totalTransactions' => (int) (clone $ledgerEntries)->count(),
        'transactions' => $transactions,
        'transactionTypeOptions' => $transactionTypeOptions,
        'periodOptions' => $periodOptions,
        'selectedTransactionType' => $selectedTransactionType,
        'selectedPeriod' => $selectedPeriod,
    ]);
})
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('reports', ReportsController::class)->name('reports.index');
    Route::livewire('cars', 'pages::cars.index')->name('cars.index');
    Route::livewire('expenses', 'pages::expenses.index')->name('expenses.index');
    Route::livewire('recurring', 'pages::recurring.index')->name('recurring.index');
    Route::livewire('quick-actions', 'pages::quick-actions.index')->name('quick-actions.index');
    Route::livewire('fuel', 'pages::fuel.index')->name('fuel.index');
    Route::livewire('maintenance', 'pages::maintenance.index')->name('maintenance.index');
    Route::livewire('reimbursements', 'pages::reimbursements.index')->name('reimbursements.index');

    Route::post('dashboard/quick-actions/{quickAction}/run', function (Request $request, QuickAction $quickAction) {
        abort_unless($quickAction->user_id === $request->user()->id, 403);

        $user = $request->user();
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'fuel_volume' => ['nullable', 'numeric', 'min:0.001'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'full_tank' => ['nullable', 'boolean'],
        ]);
        $configuredAmount = (float) $quickAction->amount;
        $amountToPost = $configuredAmount > 0
            ? $configuredAmount
            : (isset($validated['amount']) ? (float) $validated['amount'] : null);

        if ($amountToPost === null) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['quick_action_amount' => __('Please enter an amount for this quick action.')]);
        }

        $car = $quickAction->car_id !== null
            ? $user->cars()->where('is_archived', false)->find($quickAction->car_id)
            : $user->cars()->where('is_archived', false)->orderByDesc('is_default')->orderBy('id')->first();

        if ($car === null) {
            return redirect()->route('dashboard')->with('error', __('No active car is available for this quick action.'));
        }

        $accountKey = $quickAction->entry_target === 'fuel_log'
            ? 'fuel_expense'
            : 'other_expense';
        $accountName = $quickAction->entry_target === 'fuel_log'
            ? 'Fuel'
            : 'Other Expense';

        if ($quickAction->entry_target === 'fuel_log') {
            $configuredFuelVolume = $quickAction->fuel_volume !== null ? (float) $quickAction->fuel_volume : null;
            $fuelVolumeToPost = $configuredFuelVolume !== null && $configuredFuelVolume > 0
                ? $configuredFuelVolume
                : (isset($validated['fuel_volume']) ? (float) $validated['fuel_volume'] : null);

            if ($fuelVolumeToPost === null) {
                return redirect()
                    ->route('dashboard')
                    ->withErrors(['quick_action_fuel_volume' => __('Please enter fuel volume for this quick action.')]);
            }

            $odometerToPost = isset($validated['odometer'])
                ? (int) $validated['odometer']
                : (int) ($car->current_odometer ?? 0);
            $fullTankToPost = array_key_exists('full_tank', $validated)
                ? (bool) $validated['full_tank']
                : (bool) $quickAction->fuel_full_tank;

            DB::transaction(function () use ($user, $car, $quickAction, $amountToPost, $fuelVolumeToPost, $odometerToPost, $fullTankToPost): void {
                $fuelVolumeUnit = in_array($user->volume_unit, ['gallons', 'liters'], true)
                    ? $user->volume_unit
                    : ($user->measurement_system === 'metric' ? 'liters' : 'gallons');

                $fuelLog = FuelLog::query()->create([
                    'user_id' => $user->id,
                    'car_id' => $car->id,
                    'ledger_entry_id' => null,
                    'log_date' => now()->toDateString(),
                    'odometer' => $odometerToPost,
                    'volume' => $fuelVolumeToPost,
                    'volume_unit' => $fuelVolumeUnit,
                    'price_per_unit' => round($amountToPost / $fuelVolumeToPost, 3),
                    'full_tank' => $fullTankToPost,
                    'calculated_efficiency' => null,
                ]);

                $account = Account::query()->firstOrCreate(
                    ['key' => 'fuel_expense'],
                    [
                        'user_id' => null,
                        'name' => 'Fuel',
                        'group' => 'expense',
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );

                $ledgerEntry = $user->ledgerEntries()->create([
                    'car_id' => $car->id,
                    'account_id' => $account->id,
                    'entry_date' => now()->toDateString(),
                    'entry_type' => 'expense',
                    'amount' => $amountToPost,
                    'source_type' => 'fuel_log',
                    'source_id' => $fuelLog->id,
                    'reference' => 'Fuel Log',
                    'notes' => $quickAction->notes,
                ]);

                $fuelLog->update(['ledger_entry_id' => $ledgerEntry->id]);
                $car->update(['current_odometer' => max((int) ($car->current_odometer ?? 0), $odometerToPost)]);
            });
        } else {
            DB::transaction(function () use ($user, $car, $quickAction, $accountKey, $accountName, $amountToPost): void {
                $expense = Expense::query()->create([
                    'user_id' => $user->id,
                    'car_id' => $car->id,
                    'expense_category_id' => $quickAction->expense_category_id,
                    'ledger_entry_id' => null,
                    'amount' => $amountToPost,
                    'expense_date' => now()->toDateString(),
                    'odometer' => $car->current_odometer,
                    'vendor' => $quickAction->vendor,
                    'notes' => $quickAction->notes,
                    'tags' => $quickAction->tags,
                ]);

                $account = Account::query()->firstOrCreate(
                    ['key' => $accountKey],
                    [
                        'user_id' => null,
                        'name' => $accountName,
                        'group' => 'expense',
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );

                $ledgerEntry = $user->ledgerEntries()->create([
                    'car_id' => $car->id,
                    'account_id' => $account->id,
                    'entry_date' => now()->toDateString(),
                    'entry_type' => 'expense',
                    'amount' => $amountToPost,
                    'source_type' => 'expense',
                    'source_id' => $expense->id,
                    'reference' => $quickAction->vendor,
                    'notes' => $quickAction->notes,
                ]);

                $expense->update(['ledger_entry_id' => $ledgerEntry->id]);
            });
        }

        return redirect()->route('dashboard');
    })->name('dashboard.quick-actions.run');

    Route::put('dashboard/maintenance/{maintenanceRecord}', function (Request $request, MaintenanceRecord $maintenanceRecord) {
        abort_unless($maintenanceRecord->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'next_due_date' => ['nullable', 'date'],
            'next_due_odometer' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $maintenanceRecord->update([
            'next_due_date' => $validated['next_due_date'] ?? null,
            'next_due_odometer' => $validated['next_due_odometer'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('dashboard');
    })->name('dashboard.maintenance.update');

    Route::delete('dashboard/maintenance/{maintenanceRecord}', function (Request $request, MaintenanceRecord $maintenanceRecord) {
        abort_unless($maintenanceRecord->user_id === $request->user()->id, 403);
        $maintenanceRecord->delete();

        return redirect()->route('dashboard');
    })->name('dashboard.maintenance.delete');

    Route::put('dashboard/recurring/{recurringTransaction}', function (Request $request, RecurringTransaction $recurringTransaction) {
        abort_unless($recurringTransaction->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'next_entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'cadence' => ['required', 'in:monthly,quarterly,yearly'],
            'end_date' => ['nullable', 'date', 'after_or_equal:next_entry_date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $recurringTransaction->update([
            'next_entry_date' => $validated['next_entry_date'],
            'amount' => $validated['amount'],
            'cadence' => $validated['cadence'],
            'end_date' => $validated['end_date'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('dashboard');
    })->name('dashboard.recurring.update');

    Route::delete('dashboard/recurring/{recurringTransaction}', function (Request $request, RecurringTransaction $recurringTransaction) {
        abort_unless($recurringTransaction->user_id === $request->user()->id, 403);
        $recurringTransaction->delete();

        return redirect()->route('dashboard');
    })->name('dashboard.recurring.delete');
});

require __DIR__.'/settings.php';
