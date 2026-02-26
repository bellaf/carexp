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
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('odometer');
            $table->decimal('volume', 8, 3);
            $table->decimal('total_cost', 10, 2);
            $table->decimal('price_per_unit', 10, 3)->nullable();
            $table->boolean('full_tank')->default(true);
            $table->decimal('calculated_efficiency', 8, 3)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'log_date']);
            $table->index(['car_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
