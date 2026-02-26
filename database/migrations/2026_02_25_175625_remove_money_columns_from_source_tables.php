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
            $table->dropColumn('total_cost');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 2)->after('volume');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('odometer');
        });

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->after('reimbursed_date');
        });
    }
};
