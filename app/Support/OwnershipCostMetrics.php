<?php

namespace App\Support;

use App\Models\Car;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

class OwnershipCostMetrics
{
    /**
     * @param  Collection<int, LedgerEntry>  $ledgerEntries
     * @return array{
     *     distance_value: ?float,
     *     expense_total_value: float,
     *     income_total_value: float,
     *     net_cost_value: float,
     *     fuel_cost_value: float,
     *     maintenance_cost_value: float,
     *     purchase_price_value: float,
     *     sale_price_value: float,
     *     capital_cost_value: float,
     *     total_ownership_cost_value: float,
     *     distance_display: string,
     *     net_cost_per_distance_display: string,
     *     fuel_cost_per_distance_display: string,
     *     maintenance_cost_per_distance_display: string,
     *     total_ownership_cost_per_distance_display: string,
     *     unit_label: string
     * }
     */
    public static function forCar(Car $car, Collection $ledgerEntries, string $currencyCode, string $measurementSystem): array
    {
        $distance = self::distanceTravelled($car);
        $expenseTotal = (float) $ledgerEntries->where('entry_type', 'expense')->sum('amount');
        $incomeTotal = (float) $ledgerEntries->where('entry_type', 'income')->sum('amount');
        $netCost = $expenseTotal - $incomeTotal;
        $fuelCost = (float) $ledgerEntries
            ->where('entry_type', 'expense')
            ->filter(fn (LedgerEntry $entry): bool => $entry->account?->key === 'fuel_expense')
            ->sum('amount');
        $maintenanceCost = (float) $ledgerEntries
            ->where('entry_type', 'expense')
            ->filter(fn (LedgerEntry $entry): bool => in_array($entry->account?->key, ['maintenance_expense', 'repairs_expense', 'inspection_mot_expense'], true))
            ->sum('amount');
        $purchasePrice = (float) ($car->purchase_price ?? 0);
        $salePrice = (float) ($car->sale_price ?? 0);
        $capitalCost = $purchasePrice - $salePrice;
        $totalOwnershipCost = $netCost + $capitalCost;

        $unitLabel = $measurementSystem === 'metric' ? 'km' : 'mi';

        return [
            'distance_value' => $distance,
            'expense_total_value' => $expenseTotal,
            'income_total_value' => $incomeTotal,
            'net_cost_value' => $netCost,
            'fuel_cost_value' => $fuelCost,
            'maintenance_cost_value' => $maintenanceCost,
            'purchase_price_value' => $purchasePrice,
            'sale_price_value' => $salePrice,
            'capital_cost_value' => $capitalCost,
            'total_ownership_cost_value' => $totalOwnershipCost,
            'distance_display' => $distance !== null ? number_format($distance, 0).' '.$unitLabel : 'N/A',
            'net_cost_per_distance_display' => self::formatPerDistance($netCost, $distance, $currencyCode, $unitLabel),
            'fuel_cost_per_distance_display' => self::formatPerDistance($fuelCost, $distance, $currencyCode, $unitLabel),
            'maintenance_cost_per_distance_display' => self::formatPerDistance($maintenanceCost, $distance, $currencyCode, $unitLabel),
            'total_ownership_cost_per_distance_display' => self::formatPerDistance($totalOwnershipCost, $distance, $currencyCode, $unitLabel),
            'unit_label' => $unitLabel,
        ];
    }

    public static function distanceTravelled(Car $car): ?float
    {
        $startingOdometer = self::startingOdometer($car);
        $endingOdometer = self::endingOdometer($car);

        if ($startingOdometer === null || $endingOdometer === null) {
            return null;
        }

        $distance = (float) $endingOdometer - (float) $startingOdometer;

        return $distance > 0 ? $distance : null;
    }

    public static function formatPerDistance(float $cost, ?float $distance, string $currencyCode, string $unitLabel): string
    {
        if ($distance === null || $distance <= 0) {
            return 'N/A';
        }

        return CurrencyFormatter::format($cost / $distance, $currencyCode).'/'.$unitLabel;
    }

    public static function startingOdometer(Car $car): ?int
    {
        $candidates = collect([
            $car->purchase_odometer,
            $car->fuelLogs()->whereNotNull('odometer')->orderBy('log_date')->orderBy('id')->value('odometer'),
            $car->expenses()->whereNotNull('odometer')->orderBy('expense_date')->orderBy('id')->value('odometer'),
            $car->maintenanceRecords()->whereNotNull('odometer')->orderBy('service_date')->orderBy('id')->value('odometer'),
        ])->filter(fn ($value): bool => $value !== null);

        if ($candidates->isEmpty()) {
            return null;
        }

        return (int) $candidates->min();
    }

    public static function endingOdometer(Car $car): ?int
    {
        if ($car->sale_odometer !== null) {
            return (int) $car->sale_odometer;
        }

        $candidates = collect([
            $car->current_odometer,
            $car->fuelLogs()->whereNotNull('odometer')->orderByDesc('log_date')->orderByDesc('id')->value('odometer'),
            $car->expenses()->whereNotNull('odometer')->orderByDesc('expense_date')->orderByDesc('id')->value('odometer'),
            $car->maintenanceRecords()->whereNotNull('odometer')->orderByDesc('service_date')->orderByDesc('id')->value('odometer'),
        ])->filter(fn ($value): bool => $value !== null);

        if ($candidates->isEmpty()) {
            return null;
        }

        return (int) $candidates->max();
    }
}
