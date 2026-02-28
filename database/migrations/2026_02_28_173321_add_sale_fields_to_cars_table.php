<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->date('sale_date')->nullable()->after('current_odometer');
            $table->decimal('sale_price', 10, 2)->nullable()->after('sale_date');
            $table->unsignedInteger('sale_odometer')->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['sale_date', 'sale_price', 'sale_odometer']);
        });
    }
};
