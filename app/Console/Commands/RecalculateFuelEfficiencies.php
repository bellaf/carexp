<?php

namespace App\Console\Commands;

use App\Models\FuelLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RecalculateFuelEfficiencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recalculate-fuel-efficiencies
        {--user-id= : Recalculate fuel logs for a single user}
        {--car-id= : Recalculate fuel logs for a single car}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate stored fuel efficiency values for existing fuel logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $carId = $this->option('car-id') !== null ? (int) $this->option('car-id') : null;

        if ($userId !== null && $userId <= 0) {
            $this->error('When provided, --user-id must be a positive integer.');

            return self::FAILURE;
        }

        if ($carId !== null && $carId <= 0) {
            $this->error('When provided, --car-id must be a positive integer.');

            return self::FAILURE;
        }

        $users = User::query()
            ->when($userId !== null, fn ($query) => $query->whereKey($userId))
            ->whereHas('fuelLogs', fn ($query) => $query->when($carId !== null, fn ($carQuery) => $carQuery->where('car_id', $carId)))
            ->get();

        if ($userId !== null && $users->isEmpty()) {
            $this->error("User {$userId} not found or has no matching fuel logs.");

            return self::FAILURE;
        }

        $carCount = 0;
        $fuelLogCount = 0;
        $updatedCount = 0;

        $users->each(function (User $user) use ($carId, &$carCount, &$fuelLogCount, &$updatedCount): void {
            $userCarIds = $user->fuelLogs()
                ->when($carId !== null, fn ($query) => $query->where('car_id', $carId))
                ->distinct()
                ->pluck('car_id');

            $userCarIds->each(function (int $currentCarId) use ($user, &$carCount, &$fuelLogCount, &$updatedCount): void {
                $fuelLogs = $user->fuelLogs()
                    ->where('car_id', $currentCarId)
                    ->orderBy('odometer')
                    ->orderBy('id')
                    ->get();

                if ($fuelLogs->isEmpty()) {
                    return;
                }

                $carCount++;
                $fuelLogCount += $fuelLogs->count();
                $updatedCount += $this->recalculateFuelEfficiencies($fuelLogs, $user->measurement_system);
            });
        });

        if ($carId !== null && $carCount === 0) {
            $this->error("Car {$carId} not found or has no matching fuel logs.");

            return self::FAILURE;
        }

        $this->info('Fuel efficiency recalculation complete.');
        $this->line("Users processed: {$users->count()}");
        $this->line("Cars processed: {$carCount}");
        $this->line("Fuel logs scanned: {$fuelLogCount}");
        $this->line("Fuel logs updated: {$updatedCount}");

        return self::SUCCESS;
    }

    private function recalculateFuelEfficiencies(Collection $fuelLogs, string $measurementSystem): int
    {
        $previousLog = null;
        $updatedCount = 0;

        $fuelLogs->each(function (FuelLog $fuelLog) use ($measurementSystem, &$previousLog, &$updatedCount): void {
            $efficiency = null;

            if ($fuelLog->full_tank && $previousLog !== null && $previousLog->full_tank) {
                $distance = (int) $fuelLog->odometer - (int) $previousLog->odometer;
                $volumeForEfficiency = $this->volumeForMeasurementSystem((float) $fuelLog->volume, (string) $fuelLog->volume_unit, $measurementSystem);

                if ($distance > 0 && $volumeForEfficiency > 0) {
                    $efficiency = round($distance / $volumeForEfficiency, 3);
                }
            }

            $currentEfficiency = $fuelLog->calculated_efficiency !== null ? (float) $fuelLog->calculated_efficiency : null;

            if ($currentEfficiency !== $efficiency) {
                $fuelLog->update(['calculated_efficiency' => $efficiency]);
                $updatedCount++;
            }

            $previousLog = $fuelLog;
        });

        return $updatedCount;
    }

    private function volumeForMeasurementSystem(float $volume, string $volumeUnit, string $measurementSystem): float
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
