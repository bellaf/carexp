<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('reimbursement_allocations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('reimbursement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reimbursement_ledger_entry_id')->constrained('ledger_entries')->cascadeOnDelete();
            $table->foreignId('expense_ledger_entry_id')->constrained('ledger_entries')->cascadeOnDelete();
            $table->decimal('amount_allocated', 10, 2);
            $table->timestamp('allocated_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'allocated_at']);
        });
    }
};
