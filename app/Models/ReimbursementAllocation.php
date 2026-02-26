<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementAllocation extends Model
{
    /** @use HasFactory<\Database\Factories\ReimbursementAllocationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'reimbursement_ledger_entry_id',
        'expense_ledger_entry_id',
        'amount_allocated',
        'allocated_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_allocated' => 'decimal:2',
            'allocated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reimbursementLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reimbursement_ledger_entry_id');
    }

    public function expenseLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'expense_ledger_entry_id');
    }
}
