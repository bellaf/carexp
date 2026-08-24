<?php

namespace App\Support;

use App\Models\Car;
use App\Models\FuelLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OdometerAnomalyDetector
{
    private const BASELINE_INTERVAL_COUNT = 8;

    private const MINIMUM_BASELINE_INTERVAL_COUNT = 4;

    /**
     * @return array{
     *     status: 'ok'|'warning'|'error',
     *     message: string|null,
     *     fingerprint: string|null,
     *     previous_odometer: int|null,
     *     next_odometer: int|null,
     *     distance: int|null,
     *     typical_interval: float|null,
     *     warning_threshold: float|null
     * }
     */
    public function analyze(
        Car $car,
        CarbonInterface|string $logDate,
        int $odometer,
        ?int $ignoredFuelLogId = null,
    ): array {
        $date = $logDate instanceof CarbonInterface
            ? CarbonImmutable::instance($logDate)->startOfDay()
            : CarbonImmutable::parse($logDate)->startOfDay();

        $fuelLogs = $car->fuelLogs()
            ->when($ignoredFuelLogId !== null, fn ($query) => $query->whereKeyNot($ignoredFuelLogId))
            ->orderBy('log_date')
            ->orderBy('id')
            ->get(['id', 'log_date', 'odometer']);

        $previousLogs = $fuelLogs
            ->filter(fn (FuelLog $fuelLog): bool => $this->isBeforeCandidate($fuelLog, $date, $ignoredFuelLogId))
            ->values();
        $nextLog = $fuelLogs
            ->first(fn (FuelLog $fuelLog): bool => $this->isAfterCandidate($fuelLog, $date, $ignoredFuelLogId));
        $previousLog = $previousLogs->last();
        $previousOdometer = $previousLog instanceof FuelLog ? (int) $previousLog->odometer : null;
        $nextOdometer = $nextLog instanceof FuelLog ? (int) $nextLog->odometer : null;

        if ($previousOdometer !== null && $odometer < $previousOdometer) {
            return $this->result(
                status: 'error',
                message: sprintf(
                    'The odometer cannot be lower than the previous reading of %s on %s.',
                    number_format($previousOdometer),
                    $previousLog->log_date->format('d-m-Y'),
                ),
                previousOdometer: $previousOdometer,
                nextOdometer: $nextOdometer,
                distance: $odometer - $previousOdometer,
            );
        }

        if ($nextOdometer !== null && $odometer > $nextOdometer) {
            return $this->result(
                status: 'error',
                message: sprintf(
                    'The odometer cannot be higher than the following reading of %s on %s.',
                    number_format($nextOdometer),
                    $nextLog->log_date->format('d-m-Y'),
                ),
                previousOdometer: $previousOdometer,
                nextOdometer: $nextOdometer,
                distance: $previousOdometer !== null ? $odometer - $previousOdometer : null,
            );
        }

        if ($previousOdometer === null) {
            return $this->result();
        }

        $distance = $odometer - $previousOdometer;
        $intervals = $this->recentPositiveIntervals($previousLogs);

        if ($intervals->count() >= self::MINIMUM_BASELINE_INTERVAL_COUNT) {
            $typicalInterval = $this->median($intervals);
            $medianAbsoluteDeviation = $this->median(
                $intervals->map(fn (int $interval): float => abs($interval - $typicalInterval)),
            );
            $warningThreshold = max(
                $typicalInterval * 2,
                $typicalInterval + (6 * $medianAbsoluteDeviation),
            );

            if ($distance > $warningThreshold) {
                return $this->warningResult(
                    car: $car,
                    date: $date,
                    odometer: $odometer,
                    ignoredFuelLogId: $ignoredFuelLogId,
                    previousOdometer: $previousOdometer,
                    nextOdometer: $nextOdometer,
                    distance: $distance,
                    typicalInterval: $typicalInterval,
                    warningThreshold: $warningThreshold,
                );
            }
        }

        if ($distance >= 5000 && $odometer >= ($previousOdometer * 3)) {
            return $this->warningResult(
                car: $car,
                date: $date,
                odometer: $odometer,
                ignoredFuelLogId: $ignoredFuelLogId,
                previousOdometer: $previousOdometer,
                nextOdometer: $nextOdometer,
                distance: $distance,
                typicalInterval: null,
                warningThreshold: null,
            );
        }

        return $this->result(
            previousOdometer: $previousOdometer,
            nextOdometer: $nextOdometer,
            distance: $distance,
        );
    }

    private function isBeforeCandidate(FuelLog $fuelLog, CarbonImmutable $date, ?int $ignoredFuelLogId): bool
    {
        if ($fuelLog->log_date->lt($date)) {
            return true;
        }

        if ($fuelLog->log_date->gt($date)) {
            return false;
        }

        return $ignoredFuelLogId === null || $fuelLog->id < $ignoredFuelLogId;
    }

    private function isAfterCandidate(FuelLog $fuelLog, CarbonImmutable $date, ?int $ignoredFuelLogId): bool
    {
        if ($fuelLog->log_date->gt($date)) {
            return true;
        }

        if ($fuelLog->log_date->lt($date) || $ignoredFuelLogId === null) {
            return false;
        }

        return $fuelLog->id > $ignoredFuelLogId;
    }

    /**
     * @param  Collection<int, FuelLog>  $fuelLogs
     * @return Collection<int, int>
     */
    private function recentPositiveIntervals(Collection $fuelLogs): Collection
    {
        return $fuelLogs
            ->values()
            ->map(function (FuelLog $fuelLog, int $index) use ($fuelLogs): ?int {
                if ($index === 0) {
                    return null;
                }

                $previousLog = $fuelLogs->get($index - 1);
                $interval = (int) $fuelLog->odometer - (int) $previousLog->odometer;

                return $interval > 0 ? $interval : null;
            })
            ->filter(fn (?int $interval): bool => $interval !== null)
            ->take(-self::BASELINE_INTERVAL_COUNT)
            ->values();
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function median(Collection $values): float
    {
        $sortedValues = $values->sort()->values();
        $middle = intdiv($sortedValues->count(), 2);

        if ($sortedValues->count() % 2 === 1) {
            return (float) $sortedValues->get($middle);
        }

        return ((float) $sortedValues->get($middle - 1) + (float) $sortedValues->get($middle)) / 2;
    }

    /**
     * @return array{
     *     status: 'warning',
     *     message: string,
     *     fingerprint: string,
     *     previous_odometer: int,
     *     next_odometer: int|null,
     *     distance: int,
     *     typical_interval: float|null,
     *     warning_threshold: float|null
     * }
     */
    private function warningResult(
        Car $car,
        CarbonImmutable $date,
        int $odometer,
        ?int $ignoredFuelLogId,
        int $previousOdometer,
        ?int $nextOdometer,
        int $distance,
        ?float $typicalInterval,
        ?float $warningThreshold,
    ): array {
        $unit = $car->user()->value('measurement_system') === 'metric' ? 'kilometres' : 'miles';
        $message = $typicalInterval !== null
            ? sprintf(
                'This reading is %s %s above the previous entry. Your recent typical fill-up interval is %s %s. Check for an extra digit or confirm that fill-ups were not recorded.',
                number_format($distance),
                $unit,
                number_format($typicalInterval),
                $unit,
            )
            : sprintf(
                'This reading jumps by %s %s from the previous entry. Check for an extra digit before saving.',
                number_format($distance),
                $unit,
            );

        return $this->result(
            status: 'warning',
            message: $message,
            fingerprint: hash('sha256', implode('|', [
                $car->id,
                $date->format('Y-m-d'),
                $odometer,
                $ignoredFuelLogId ?? 'new',
                $previousOdometer,
                $nextOdometer ?? 'none',
                $typicalInterval ?? 'none',
            ])),
            previousOdometer: $previousOdometer,
            nextOdometer: $nextOdometer,
            distance: $distance,
            typicalInterval: $typicalInterval,
            warningThreshold: $warningThreshold,
        );
    }

    /**
     * @return array{
     *     status: 'ok'|'warning'|'error',
     *     message: string|null,
     *     fingerprint: string|null,
     *     previous_odometer: int|null,
     *     next_odometer: int|null,
     *     distance: int|null,
     *     typical_interval: float|null,
     *     warning_threshold: float|null
     * }
     */
    private function result(
        string $status = 'ok',
        ?string $message = null,
        ?string $fingerprint = null,
        ?int $previousOdometer = null,
        ?int $nextOdometer = null,
        ?int $distance = null,
        ?float $typicalInterval = null,
        ?float $warningThreshold = null,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'fingerprint' => $fingerprint,
            'previous_odometer' => $previousOdometer,
            'next_odometer' => $nextOdometer,
            'distance' => $distance,
            'typical_interval' => $typicalInterval,
            'warning_threshold' => $warningThreshold,
        ];
    }
}
