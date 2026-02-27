<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReimbursementAllocation>
 */
class ReimbursementAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => static function (array $attributes): int {
                return LedgerEntry::query()->findOrFail($attributes['reimbursement_ledger_entry_id'])->user_id;
            },
            'reimbursement_ledger_entry_id' => LedgerEntry::factory()->state([
                'entry_type' => 'income',
            ]),
            'expense_ledger_entry_id' => LedgerEntry::factory()->state([
                'entry_type' => 'expense',
            ]),
            'amount_allocated' => $this->faker->randomFloat(2, 1, 500),
            'allocated_at' => now(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
