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

                $volume = self::volumeForMeasurementSystem((float) $fuelLog->volume, (string) $fuelLog->volume_unit, $measurementSystem);

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

    private static function volumeForMeasurementSystem(float $volume, string $volumeUnit, string $measurementSystem): float
    {
        if ($measurementSystem === 'metric') {
            return $volumeUnit === 'liters'
                ? $volume
                : ($volume * 3.785411784);
        }

        return $volumeUnit === 'gallons'
            ? $volume
            : ($volume * 0.2641720524);
    }
}
