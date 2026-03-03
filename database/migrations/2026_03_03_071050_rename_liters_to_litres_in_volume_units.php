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
            $table->string('volume_unit', 16)->default('gallons')->change();
        });

        DB::table('users')
            ->where('volume_unit', 'liters')
            ->update(['volume_unit' => 'litres']);

        DB::table('fuel_logs')
            ->where('volume_unit', 'liters')
            ->update(['volume_unit' => 'litres']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('volume_unit', 'litres')
            ->update(['volume_unit' => 'liters']);

        DB::table('fuel_logs')
            ->where('volume_unit', 'litres')
            ->update(['volume_unit' => 'liters']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('volume_unit', 16)->default('gallons')->change();
        });
    }
};
