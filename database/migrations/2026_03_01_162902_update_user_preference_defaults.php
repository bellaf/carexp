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
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_currency', 3)->default('GBP')->change();
            $table->string('measurement_system', 16)->default('imperial')->change();
            $table->string('volume_unit', 16)->default('gallons')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_currency', 3)->default('USD')->change();
            $table->string('measurement_system', 16)->default('imperial')->change();
            $table->string('volume_unit', 16)->default('gallons')->change();
        });
    }
};
