<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MaintenanceRecord extends Model
{
    /** @use HasFactory<\Database\Factories\MaintenanceRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'car_id',
        'ledger_entry_id',
        'service_type',
        'provider',
        'service_date',
        'odometer',
        'notes',
        'next_due_date',
        'next_due_odometer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $record): void {
            $record->attachments->each(fn (Attachment $attachment) => app(\App\Support\AttachmentManager::class)->delete($attachment));
        });
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
