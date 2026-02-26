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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('expense_category_id')->constrained()->nullOnDelete();
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->foreignId('ledger_entry_id')->nullable()->after('expense_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_entry_id');
        });
    }
};
