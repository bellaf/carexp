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
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('car_id')->constrained()->nullOnDelete();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('car_id')->constrained()->nullOnDelete();
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('car_id')->constrained()->nullOnDelete();
        });
    }
};
