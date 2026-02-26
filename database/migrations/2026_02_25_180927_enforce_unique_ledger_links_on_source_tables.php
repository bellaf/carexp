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
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->unique('ledger_entry_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->unique('ledger_entry_id');
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->unique('ledger_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropUnique(['ledger_entry_id']);
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropUnique(['ledger_entry_id']);
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropUnique(['ledger_entry_id']);
        });
    }
};
