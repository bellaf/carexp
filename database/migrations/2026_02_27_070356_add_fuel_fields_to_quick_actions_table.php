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
        Schema::table('quick_actions', function (Blueprint $table) {
            $table->string('entry_target', 32)->default('expense')->after('name');
            $table->decimal('fuel_volume', 8, 3)->nullable()->after('amount');
            $table->boolean('fuel_full_tank')->default(true)->after('fuel_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quick_actions', function (Blueprint $table) {
            $table->dropColumn([
                'entry_target',
                'fuel_volume',
                'fuel_full_tank',
            ]);
        });
    }
};
