<?php

namespace App\Support;

use App\Models\FuelLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FuelCostReport
{
    /** @return array<string, mixed> */
    public static function build(Collection $logs, string $currency, string $measurementSystem, ?CarbonImmutable $start, ?CarbonImmutable $end): array
    {
        $receipts = $logs->map(function (FuelLog $log): array {
            $litres = (float) $log->volume * ($log->volume_unit === 'gallons' ? 4.54609 : 1);
            $cost = $log->ledgerEntry?->amount !== null
                ? (float) $log->ledgerEntry->amount
                : ($log->price_per_unit !== null ? (float) $log->price_per_unit * (float) $log->volume : null);

            return ['month' => $log->log_date->format('Y-m'), 'litres' => $litres, 'cost' => $cost, 'price' => $cost !== null && $litres > 0 ? $cost * 100 / $litres : null];
        });
        $priced = $receipts->whereNotNull('price');
        $spend = (float) $receipts->sum('cost');
        $pricedLitres = (float) $priced->sum('litres');
        $price = $pricedLitres > 0 ? (float) $priced->sum('cost') * 100 / $pricedLitres : null;
        $unit = strtoupper($currency) === 'GBP' ? 'p/L' : strtoupper($currency).' cents/L';
        $months = collect();

        if ($logs->isNotEmpty()) {
            $first = ($start ?? CarbonImmutable::instance($logs->min('log_date')))->startOfMonth();
            $last = ($end ?? CarbonImmutable::instance($logs->max('log_date')))->startOfMonth();
            $groups = $receipts->groupBy('month');
            $logGroups = $logs->groupBy(fn (FuelLog $log): string => $log->log_date->format('Y-m'));

            for ($month = $first; $month->lte($last); $month = $month->addMonth()) {
                $entries = $groups->get($month->format('Y-m'), collect());
                $monthPriced = $entries->whereNotNull('price');
                $litres = (float) $monthPriced->sum('litres');
                $months->push([
                    'month' => $month->format('M Y'),
                    'spend' => $entries->isNotEmpty() && $entries->whereNotNull('cost')->isEmpty() ? null : (float) $entries->sum('cost'),
                    'price' => $litres > 0 ? (float) $monthPriced->sum('cost') * 100 / $litres : null,
                    'litres' => (float) $entries->sum('litres'),
                    'fills' => $entries->count(),
                    'efficiency' => FuelEfficiencyCalculator::averageForLogs($logGroups->get($month->format('Y-m'), collect()), $measurementSystem),
                ]);
            }
        }

        return [
            'count' => $logs->count(), 'spend' => $spend,
            'litres' => (float) $receipts->sum('litres'), 'price' => $price,
            'lowest_price' => $priced->min('price'), 'highest_price' => $priced->max('price'),
            'average_fill_cost' => $receipts->whereNotNull('cost')->count() > 0 ? $spend / $receipts->whereNotNull('cost')->count() : null,
            'average_fill_litres' => $logs->count() > 0 ? (float) $receipts->sum('litres') / $logs->count() : null,
            'missing_mileage' => $logs->whereNull('odometer')->count(),
            'missing_cost' => $receipts->whereNull('cost')->count(),
            'efficiency' => FuelEfficiencyCalculator::averageForLogs($logs, $measurementSystem),
            'combined_intervals' => $logs->filter(fn (FuelLog $log): bool => $log->efficiency_fill_count > 1)->count(),
            'price_unit' => $unit, 'months' => $months,
            'charts' => [
                ['key' => 'spend', 'title' => 'Monthly fuel spending', 'unit' => CurrencyFormatter::symbol($currency)],
                ['key' => 'price', 'title' => 'Fuel price per litre', 'unit' => $unit],
                ['key' => 'litres', 'title' => 'Litres purchased', 'unit' => 'L'],
                ['key' => 'efficiency', 'title' => 'Fuel efficiency', 'unit' => $measurementSystem === 'metric' ? 'KM/L' : 'MPG'],
            ],
        ];
    }
}
