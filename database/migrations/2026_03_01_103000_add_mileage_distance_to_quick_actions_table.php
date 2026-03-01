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
            $table->unsignedInteger('mileage_distance')->nullable()->after('mileage_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quick_actions', function (Blueprint $table) {
            $table->dropColumn('mileage_distance');
        });
    }
};
