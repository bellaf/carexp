<?php

namespace App\Support;

use App\Models\Car;

class LatestOdometerResolver
{
    public function forCar(Car $car): ?int
    {
        $candidates = collect([
            $car->current_odometer,
            $car->fuelLogs()->whereNotNull('odometer')->orderByDesc('log_date')->orderByDesc('id')->value('odometer'),
            $car->expenses()->whereNotNull('odometer')->orderByDesc('expense_date')->orderByDesc('id')->value('odometer'),
            $car->maintenanceRecords()->whereNotNull('odometer')->orderByDesc('service_date')->orderByDesc('id')->value('odometer'),
            $car->mileageLogs()->orderByDesc('log_date')->orderByDesc('id')->value('end_odometer'),
        ])->filter(fn ($value): bool => $value !== null);

        if ($candidates->isEmpty()) {
            return null;
        }

        return (int) $candidates->max();
    }
}
