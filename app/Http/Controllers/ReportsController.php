<?php

namespace App\Http\Controllers;

use App\Models\FuelLog;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\VehicleObligation;
use App\Support\CurrencyFormatter;
use App\Support\FuelEfficiencyCalculator;
use App\Support\OwnershipCostMetrics;
use App\Support\VehicleObligationStatus;
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
            'obligations' => 'Obligations',
            'ownership' => 'Ownership Metrics',
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

        [$startDate, $endDate] = $selectedReport === 'ownership'
            ? [null, null]
            : $this->resolveDateRange($selectedPeriod, $selectedYear);

        $ledgerEntries = $this->filteredLedgerEntries($user, $selectedCarId, $startDate, $endDate);
        $fuelLogs = $this->filteredFuelLogs($user, $selectedCarId, $startDate, $endDate);
        $vehicleObligations = $this->filteredVehicleObligations($user, $selectedCarId, $startDate, $endDate);

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
            'fuelSummary' => $this->fuelSummary($fuelLogs, $user->preferred_currency, $user->measurement_system),
            'fuelMonthlyRows' => $this->fuelMonthlyTrend($fuelLogs, $user->preferred_currency, $user->measurement_system),
            'obligationSummary' => $this->obligationSummary($vehicleObligations, $user->preferred_currency),
            'obligationRows' => $this->obligationRows($vehicleObligations, $user->preferred_currency),
            'ownershipRows' => $this->ownershipRows($cars, $selectedCarId, $user->preferred_currency, $user->measurement_system),
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

    private function filteredVehicleObligations(User $user, ?int $carId, ?CarbonImmutable $startDate, ?CarbonImmutable $endDate): Collection
    {
        return $user->vehicleObligations()
            ->with(['car', 'ledgerEntry'])
            ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
            ->when($startDate !== null, fn ($query) => $query->whereDate('due_date', '>=', $startDate->toDateString()))
            ->when($endDate !== null, fn ($query) => $query->whereDate('due_date', '<=', $endDate->toDateString()))
            ->orderBy('due_date')
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
    private function fuelSummary(Collection $fuelLogs, string $currencyCode, string $measurementSystem): array
    {
        $totalSpend = (float) $fuelLogs->sum(fn (FuelLog $fuelLog): float => (float) ($fuelLog->ledgerEntry?->amount ?? 0));
        $totalVolume = (float) $fuelLogs->sum('volume');
        $averageEfficiency = FuelEfficiencyCalculator::averageForLogs($fuelLogs, $measurementSystem);

        return [
            'fill_count' => $fuelLogs->count(),
            'total_spend' => CurrencyFormatter::format($totalSpend, $currencyCode),
            'total_volume' => number_format($totalVolume, 3),
            'average_price' => $totalVolume > 0 ? CurrencyFormatter::format($totalSpend / $totalVolume, $currencyCode) : CurrencyFormatter::format(0, $currencyCode),
            'average_efficiency' => $averageEfficiency !== null ? number_format((float) $averageEfficiency, 3) : 'N/A',
        ];
    }

    private function fuelMonthlyTrend(Collection $fuelLogs, string $currencyCode, string $measurementSystem): Collection
    {
        return $fuelLogs
            ->groupBy(fn (FuelLog $fuelLog): string => $fuelLog->log_date->format('Y-m'))
            ->map(function (Collection $entries, string $monthKey) use ($currencyCode, $measurementSystem): array {
                $totalSpend = (float) $entries->sum(fn (FuelLog $fuelLog): float => (float) ($fuelLog->ledgerEntry?->amount ?? 0));
                $totalVolume = (float) $entries->sum('volume');
                $averageEfficiency = FuelEfficiencyCalculator::averageForLogs($entries, $measurementSystem);

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

    /**
     * @return array<string, string|int>
     */
    private function obligationSummary(Collection $vehicleObligations, string $currencyCode): array
    {
        $overdueCount = $vehicleObligations
            ->filter(fn (VehicleObligation $obligation): bool => VehicleObligationStatus::status($obligation) === 'overdue')
            ->count();

        $dueSoonCount = $vehicleObligations
            ->filter(fn (VehicleObligation $obligation): bool => VehicleObligationStatus::status($obligation) === 'due_soon')
            ->count();

        $activeCount = $vehicleObligations->where('is_active', true)->count();
        $totalCost = (float) $vehicleObligations->sum('amount');

        return [
            'active_count' => $activeCount,
            'due_soon_count' => $dueSoonCount,
            'overdue_count' => $overdueCount,
            'total_cost' => CurrencyFormatter::format($totalCost, $currencyCode),
        ];
    }

    private function obligationRows(Collection $vehicleObligations, string $currencyCode): Collection
    {
        return $vehicleObligations
            ->map(function (VehicleObligation $obligation) use ($currencyCode): array {
                $typeLabel = match ($obligation->obligation_type) {
                    'insurance' => 'Insurance',
                    'tax' => 'Tax / Registration',
                    default => 'MOT / Inspection',
                };

                return [
                    'type' => $typeLabel,
                    'car' => trim(collect([$obligation->car?->year, $obligation->car?->make, $obligation->car?->model])->filter()->implode(' ')) ?: 'N/A',
                    'due_date' => $obligation->due_date->format('d-m-Y'),
                    'provider' => $obligation->provider ?: 'N/A',
                    'reference' => $obligation->reference ?: 'N/A',
                    'cost' => CurrencyFormatter::format($obligation->amount, $currencyCode),
                    'status' => VehicleObligationStatus::label($obligation),
                    'status_key' => VehicleObligationStatus::status($obligation),
                ];
            })
            ->values();
    }

    private function ownershipRows(Collection $cars, ?int $selectedCarId, string $currencyCode, string $measurementSystem): Collection
    {
        return $cars
            ->when($selectedCarId !== null, fn (Collection $collection): Collection => $collection->where('id', $selectedCarId)->values())
            ->map(function ($car) use ($currencyCode, $measurementSystem): array {
                $ledgerEntries = $car->ledgerEntries()->with('account')->get();
                $metrics = OwnershipCostMetrics::forCar($car, $ledgerEntries, $currencyCode, $measurementSystem);

                return [
                    'car' => trim(collect([$car->year, $car->make, $car->model])->filter()->implode(' ')) ?: 'N/A',
                    'status' => $car->sale_date !== null ? 'Sold' : 'Owned',
                    'distance' => $metrics['distance_display'],
                    'purchase_price' => CurrencyFormatter::format($metrics['purchase_price_value'], $currencyCode),
                    'sale_price' => $car->sale_date !== null ? CurrencyFormatter::format($metrics['sale_price_value'], $currencyCode) : 'N/A',
                    'expense_total' => CurrencyFormatter::format($metrics['expense_total_value'], $currencyCode),
                    'income_total' => CurrencyFormatter::format($metrics['income_total_value'], $currencyCode),
                    'net_cost' => CurrencyFormatter::format($metrics['net_cost_value'], $currencyCode),
                    'total_ownership_cost' => CurrencyFormatter::format($metrics['total_ownership_cost_value'], $currencyCode),
                    'net_cost_per_distance' => $metrics['net_cost_per_distance_display'],
                    'fuel_cost_per_distance' => $metrics['fuel_cost_per_distance_display'],
                    'maintenance_cost_per_distance' => $metrics['maintenance_cost_per_distance_display'],
                    'total_ownership_cost_per_distance' => $metrics['total_ownership_cost_per_distance_display'],
                ];
            })
            ->values();
    }
}
