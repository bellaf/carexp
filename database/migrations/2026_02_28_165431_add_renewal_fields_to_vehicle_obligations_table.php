<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_obligations', function (Blueprint $table) {
            $table->foreignId('renewed_from_id')
                ->nullable()
                ->after('ledger_entry_id')
                ->constrained('vehicle_obligations')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_obligations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropColumn('completed_at');
        });
    }
};
