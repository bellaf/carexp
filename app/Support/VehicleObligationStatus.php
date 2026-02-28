<?php

namespace App\Support;

use App\Models\VehicleObligation;
use Carbon\CarbonInterface;

class VehicleObligationStatus
{
    public static function status(VehicleObligation $obligation, ?CarbonInterface $today = null): string
    {
        if ($obligation->completed_at !== null) {
            return 'completed';
        }

        if (! $obligation->is_active) {
            return 'inactive';
        }

        $today ??= now()->startOfDay();

        if ($obligation->due_date->lt($today)) {
            return 'overdue';
        }

        if ($obligation->due_date->lte($today->copy()->addDays(30)->endOfDay())) {
            return 'due_soon';
        }

        return 'upcoming';
    }

    public static function isUpcomingWithinDays(VehicleObligation $obligation, int $days, ?CarbonInterface $today = null): bool
    {
        if (! $obligation->is_active) {
            return false;
        }

        $today ??= now()->startOfDay();

        return $obligation->due_date->gte($today)
            && $obligation->due_date->lte($today->copy()->addDays($days)->endOfDay());
    }

    public static function label(VehicleObligation $obligation, ?CarbonInterface $today = null): string
    {
        return match (self::status($obligation, $today)) {
            'overdue' => 'Overdue',
            'due_soon' => 'Due Soon',
            'completed' => 'Completed',
            'inactive' => 'Inactive',
            default => 'Upcoming',
        };
    }
}
