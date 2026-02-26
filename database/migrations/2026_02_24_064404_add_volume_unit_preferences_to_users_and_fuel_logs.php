<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('volume_unit', 16)->default('gallons')->after('measurement_system');
        });

        DB::table('users')
            ->where('measurement_system', 'metric')
            ->update(['volume_unit' => 'liters']);

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->string('volume_unit', 16)->nullable()->after('volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('volume_unit');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('volume_unit');
        });
    }
};
