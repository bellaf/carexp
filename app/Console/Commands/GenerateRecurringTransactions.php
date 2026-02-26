<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-recurring-transactions {--date= : Generate up to this date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate due ledger entries from recurring transaction schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $runDate = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay()->toImmutable();

        $generatedCount = 0;

        $recurringTransactions = DB::table('recurring_transactions')
            ->where('is_active', true)
            ->whereDate('next_entry_date', '<=', $runDate->format('Y-m-d'))
            ->orderBy('id')
            ->get();

        foreach ($recurringTransactions as $recurringTransaction) {
            DB::transaction(function () use ($recurringTransaction, $runDate, &$generatedCount): void {
                $currentDate = CarbonImmutable::parse($recurringTransaction->next_entry_date);
                $endDate = $recurringTransaction->end_date !== null
                    ? CarbonImmutable::parse($recurringTransaction->end_date)
                    : null;

                while ($currentDate->lte($runDate) && ($endDate === null || $currentDate->lte($endDate))) {
                    DB::table('ledger_entries')->insert([
                        'user_id' => $recurringTransaction->user_id,
                        'car_id' => $recurringTransaction->car_id,
                        'account_id' => $recurringTransaction->account_id,
                        'recurring_transaction_id' => $recurringTransaction->id,
                        'entry_date' => $currentDate->format('Y-m-d'),
                        'entry_type' => $recurringTransaction->entry_type,
                        'amount' => $recurringTransaction->amount,
                        'source_type' => 'recurring',
                        'source_id' => $recurringTransaction->id,
                        'reference' => $recurringTransaction->reference,
                        'notes' => $recurringTransaction->notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $generatedCount++;
                    $currentDate = $this->nextDate($currentDate, $recurringTransaction->cadence);
                }

                DB::table('recurring_transactions')
                    ->where('id', $recurringTransaction->id)
                    ->update([
                        'next_entry_date' => $currentDate->format('Y-m-d'),
                        'last_generated_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
        }

        $this->info("Generated {$generatedCount} recurring ledger entr".($generatedCount === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }

    protected function nextDate(CarbonImmutable $date, string $cadence): CarbonImmutable
    {
        return match ($cadence) {
            'monthly' => $date->addMonthNoOverflow(),
            'quarterly' => $date->addMonthsNoOverflow(3),
            'yearly' => $date->addYearNoOverflow(),
            default => $date->addMonthNoOverflow(),
        };
    }
}
