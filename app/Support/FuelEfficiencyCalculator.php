<?php

namespace App\Support;

use App\Models\FuelLog;
use Illuminate\Support\Collection;

class FuelEfficiencyCalculator
{
    public static function averageForLogs(Collection $fuelLogs, string $measurementSystem): ?float
    {
        $weightedEfficiencyTotal = 0.0;
        $totalVolume = 0.0;

        $fuelLogs
            ->each(function (FuelLog $fuelLog) use ($measurementSystem, &$weightedEfficiencyTotal, &$totalVolume): void {
                if (! $fuelLog->full_tank || $fuelLog->calculated_efficiency === null) {
                    return;
                }

                $volume = $fuelLog->efficiency_volume ?? self::volumeForMeasurementSystem((float) $fuelLog->volume, (string) $fuelLog->volume_unit, $measurementSystem);

                if ($volume <= 0) {
                    return;
                }

                $weightedEfficiencyTotal += ((float) $fuelLog->calculated_efficiency * $volume);
                $totalVolume += $volume;
            });

        if ($totalVolume <= 0) {
            return null;
        }

        return round($weightedEfficiencyTotal / $totalVolume, 3);
    }

    public static function recalculate(Collection $fuelLogs, string $measurementSystem): int
    {
        $anchor = null;
        $volume = 0.0;
        $fillCount = 0;
        $updatedCount = 0;

        foreach ($fuelLogs->sortBy([['log_date', 'asc'], ['id', 'asc']]) as $fuelLog) {
            $efficiency = null;
            $intervalVolume = null;
            $intervalFillCount = null;

            if ($anchor !== null) {
                $volume += self::volumeForMeasurementSystem((float) $fuelLog->volume, (string) $fuelLog->volume_unit, $measurementSystem);
                $fillCount++;
            }

            if ($fuelLog->full_tank && $fuelLog->odometer !== null) {
                if ($anchor !== null && $fuelLog->odometer > $anchor->odometer && $volume > 0) {
                    $efficiency = round(($fuelLog->odometer - $anchor->odometer) / $volume, 3);
                    $intervalVolume = round($volume, 6);
                    $intervalFillCount = $fillCount;
                }

                $anchor = $fuelLog;
                $volume = 0.0;
                $fillCount = 0;
            }

            $fuelLog->fill([
                'calculated_efficiency' => $efficiency,
                'efficiency_volume' => $intervalVolume,
                'efficiency_fill_count' => $intervalFillCount,
            ]);

            if ($fuelLog->isDirty()) {
                $fuelLog->save();
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    private static function volumeForMeasurementSystem(float $volume, string $volumeUnit, string $measurementSystem): float
    {
        if ($measurementSystem === 'metric') {
            return $volumeUnit === 'litres'
                ? $volume
                : ($volume * 4.54609);
        }

        return $volumeUnit === 'gallons'
            ? $volume
            : ($volume / 4.54609);
    }
}
