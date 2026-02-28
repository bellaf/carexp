<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\CurrencyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $reportOptions = [
            'summary' => 'Summary',
            'category' => 'Category Breakdown',
            'fuel' => 'Fuel Analysis',
        ];

        $periodOptions = [
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'year_to_date' => 'Year to Date',
            'full_year' => 'Calendar Year',
            'all_time' => 'All Time',
        ];

        $selectedReport = $request->string('report')->toString();
        $selectedPeriod = $request->string('period')->toString();
        $selectedYear = max(2000, (int) $request->integer('year', (int) now()->year));
        $selectedCarId = $request->filled('car_id') ? $request->integer('car_id') : null;

        if (! array_key_exists($selectedReport, $reportOptions)) {
            $selectedReport = 'summary';
        }

        if (! array_key_exists($selectedPeriod, $periodOptions)) {
            $selectedPeriod = 'year_to_date';
        }

        $cars = $user->cars()
            ->where('is_archived', false)
            ->orderByDesc('is_default')
            ->orderBy('make')
            ->orderBy('model')
            ->get();

        if ($selectedCarId > 0 && ! $cars->contains('id', $selectedCarId)) {
            $selectedCarId = null;
        }

        [$startDate, $endDate] = $this->resolveDateRange($selectedPeriod, $selectedYear);

        $ledgerEntries = $this->filteredLedgerEntries($user, $selectedCarId, $startDate, $endDate);
        $fuelLogs = $this->filteredFuelLogs($user, $selectedCarId, $startDate, $endDate);

        return view('reports', [
            'cars' => $cars,
            'currencyCode' => $user->preferred_currency,
            'efficiencyLabel' => $user->measurement_system === 'metric' ? 'KM/L' : 'MPG',
            'volumeLabel' => $user->volume_unit === 'liters' ? 'L' : 'gal',
            'reportOptions' => $reportOptions,
            'periodOptions' => $periodOptions,
            'selectedReport' => $selectedReport,
            'selectedPeriod' => $selectedPeriod,
            'selectedYear' => $selectedYear,
            'selectedCarId' => $selectedCarId,
            'summary' => $this->summaryMetrics($ledgerEntries, $user->preferred_currency),
            'categoryRows' => $this->categoryBreakdown($ledgerEntries, $user->preferred_currency),
            'monthlyRows' => $this->monthlyLedgerTrend($ledgerEntries, $user->preferred_currency),
            'fuelSummary' => $this->fuelSummary($fuelLogs, $user->preferred_currency),
            'fuelMonthlyRows' => $this->fuelMonthlyTrend($fuelLogs, $user->preferred_currency),
        ]);
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function resolveDateRange(string $period, int $year): array
    {
        return match ($period) {
            'this_month' => [CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()],
            'last_month' => [CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth(), CarbonImmutable::now()->subMonthNoOverflow()->endOfMonth()],
            'full_year' => [CarbonImmutable::create($year, 1, 1)->startOfDay(), CarbonImmutable::create($year, 12, 31)->endOfDay()],
            'all_time' => [null, null],
            default => [CarbonImmutable::now()->startOfYear(), CarbonImmutable::now()->endOfDay()],
        };
    }

    private function filteredLedgerEntries(User $user, ?int $carId, ?CarbonImmutable $startDate, ?CarbonImmutable $endDate): Collection
    {
        return $user->ledgerEntries()
            ->with(['account', 'car'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->when($startDate !== null, fn ($query) => $query->whereDate('entry_date', '>=', $startDate->toDateString()))
            ->when($endDate !== null, fn ($query) => $query->whereDate('entry_date', '<=', $endDate->toDateString()))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    private function filteredFuelLogs(User $user, ?int $carId, ?CarbonImmutable $startDate, ?CarbonImmutable $endDate): Collection
    {
        return $user->fuelLogs()
            ->with(['ledgerEntry', 'car'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->when($startDate !== null, fn ($query) => $query->whereDate('log_date', '>=', $startDate->toDateString()))
            ->when($endDate !== null, fn ($query) => $query->whereDate('log_date', '<=', $endDate->toDateString()))
            ->orderBy('log_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string|int|float>
     */
    private function summaryMetrics(Collection $ledgerEntries, string $currencyCode): array
    {
        $expenseTotal = (float) $ledgerEntries->where('entry_type', 'expense')->sum('amount');
        $incomeTotal = (float) $ledgerEntries->where('entry_type', 'income')->sum('amount');
        $netCost = $expenseTotal - $incomeTotal;

        return [
            'transaction_count' => $ledgerEntries->count(),
            'expense_total' => CurrencyFormatter::format($expenseTotal, $currencyCode),
            'income_total' => CurrencyFormatter::format($incomeTotal, $currencyCode),
            'net_cost' => CurrencyFormatter::format($netCost, $currencyCode),
            'net_cost_value' => $netCost,
        ];
    }

    private function categoryBreakdown(Collection $ledgerEntries, string $currencyCode): Collection
    {
        return $ledgerEntries
            ->groupBy(fn (LedgerEntry $entry): string => $entry->account?->name ?? 'Unassigned')
            ->map(function (Collection $entries, string $accountName) use ($currencyCode): array {
                $expenseTotal = (float) $entries->where('entry_type', 'expense')->sum('amount');
                $incomeTotal = (float) $entries->where('entry_type', 'income')->sum('amount');
                $netCost = $expenseTotal - $incomeTotal;

                return [
                    'category' => $accountName,
                    'expense_total' => CurrencyFormatter::format($expenseTotal, $currencyCode),
                    'income_total' => CurrencyFormatter::format($incomeTotal, $currencyCode),
                    'net_cost' => CurrencyFormatter::format($netCost, $currencyCode),
                    'sort_total' => $expenseTotal + $incomeTotal,
                ];
            })
            ->sortByDesc('sort_total')
            ->values();
    }

    private function monthlyLedgerTrend(Collection $ledgerEntries, string $currencyCode): Collection
    {
        return $ledgerEntries
            ->groupBy(fn (LedgerEntry $entry): string => $entry->entry_date->format('Y-m'))
            ->map(function (Collection $entries, string $monthKey) use ($currencyCode): array {
                $expenseTotal = (float) $entries->where('entry_type', 'expense')->sum('amount');
                $incomeTotal = (float) $entries->where('entry_type', 'income')->sum('amount');
                $netCost = $expenseTotal - $incomeTotal;

                return [
                    'month' => CarbonImmutable::createFromFormat('Y-m', $monthKey)->format('M Y'),
                    'expense_total' => CurrencyFormatter::format($expenseTotal, $currencyCode),
                    'income_total' => CurrencyFormatter::format($incomeTotal, $currencyCode),
                    'net_cost' => CurrencyFormatter::format($netCost, $currencyCode),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    /**
     * @return array<string, string|int|float>
     */
    private function fuelSummary(Collection $fuelLogs, string $currencyCode): array
    {
        $totalSpend = (float) $fuelLogs->sum(fn (FuelLog $fuelLog): float => (float) ($fuelLog->ledgerEntry?->amount ?? 0));
        $totalVolume = (float) $fuelLogs->sum('volume');
        $averageEfficiency = $fuelLogs
            ->where('full_tank', true)
            ->whereNotNull('calculated_efficiency')
            ->avg('calculated_efficiency');

        return [
            'fill_count' => $fuelLogs->count(),
            'total_spend' => CurrencyFormatter::format($totalSpend, $currencyCode),
            'total_volume' => number_format($totalVolume, 3),
            'average_price' => $totalVolume > 0 ? CurrencyFormatter::format($totalSpend / $totalVolume, $currencyCode) : CurrencyFormatter::format(0, $currencyCode),
            'average_efficiency' => $averageEfficiency !== null ? number_format((float) $averageEfficiency, 3) : 'N/A',
        ];
    }

    private function fuelMonthlyTrend(Collection $fuelLogs, string $currencyCode): Collection
    {
        return $fuelLogs
            ->groupBy(fn (FuelLog $fuelLog): string => $fuelLog->log_date->format('Y-m'))
            ->map(function (Collection $entries, string $monthKey) use ($currencyCode): array {
                $totalSpend = (float) $entries->sum(fn (FuelLog $fuelLog): float => (float) ($fuelLog->ledgerEntry?->amount ?? 0));
                $totalVolume = (float) $entries->sum('volume');
                $averageEfficiency = $entries
                    ->where('full_tank', true)
                    ->whereNotNull('calculated_efficiency')
                    ->avg('calculated_efficiency');

                return [
                    'month' => CarbonImmutable::createFromFormat('Y-m', $monthKey)->format('M Y'),
                    'fill_count' => $entries->count(),
                    'total_spend' => CurrencyFormatter::format($totalSpend, $currencyCode),
                    'total_volume' => number_format($totalVolume, 3),
                    'average_efficiency' => $averageEfficiency !== null ? number_format((float) $averageEfficiency, 3) : 'N/A',
                ];
            })
            ->sortKeysDesc()
            ->values();
    }
}
