<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table): void {
            $table->unsignedInteger('odometer')->nullable()->change();
            $table->decimal('efficiency_volume', 12, 6)->nullable();
            $table->unsignedInteger('efficiency_fill_count')->nullable();
        });
    }

    public function down(): void
    {
        if (\App\Models\FuelLog::query()->whereNull('odometer')->exists()) {
            throw new RuntimeException('Add missing fuel odometer readings before rolling back this migration.');
        }

        Schema::table('fuel_logs', function (Blueprint $table): void {
            $table->unsignedInteger('odometer')->nullable(false)->change();
            $table->dropColumn(['efficiency_volume', 'efficiency_fill_count']);
        });
    }
};
