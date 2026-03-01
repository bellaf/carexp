<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Car extends Model
{
    /** @use HasFactory<\Database\Factories\CarFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nickname',
        'year',
        'make',
        'model',
        'trim',
        'vin',
        'plate',
        'fuel_type',
        'purchase_date',
        'purchase_price',
        'purchase_odometer',
        'current_odometer',
        'sale_date',
        'sale_price',
        'sale_odometer',
        'is_archived',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'sale_date' => 'date',
            'sale_price' => 'decimal:2',
            'is_archived' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function quickActions(): HasMany
    {
        return $this->hasMany(QuickAction::class);
    }

    public function mileageLogs(): HasMany
    {
        return $this->hasMany(MileageLog::class);
    }

    public function vehicleObligations(): HasMany
    {
        return $this->hasMany(VehicleObligation::class);
    }
}
