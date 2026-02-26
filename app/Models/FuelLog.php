<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    /** @use HasFactory<\Database\Factories\FuelLogFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'car_id',
        'ledger_entry_id',
        'log_date',
        'odometer',
        'volume',
        'volume_unit',
        'price_per_unit',
        'full_tank',
        'calculated_efficiency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'volume' => 'decimal:3',
            'price_per_unit' => 'decimal:3',
            'full_tank' => 'boolean',
            'calculated_efficiency' => 'decimal:3',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
