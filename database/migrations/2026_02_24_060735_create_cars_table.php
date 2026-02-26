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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nickname')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('make');
            $table->string('model');
            $table->string('trim')->nullable();
            $table->string('vin', 32)->nullable();
            $table->string('plate', 32)->nullable();
            $table->string('fuel_type')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->unsignedInteger('purchase_odometer')->nullable();
            $table->unsignedInteger('current_odometer')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
