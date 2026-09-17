<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_plans', function (Blueprint $table) {
            // docs section 13.3: "Rencana yang sudah direalisasikan harus
            // memiliki referensi ke realisasi." Couldn't be added until now
            // — the activities table didn't exist during Milestone D.
            $table->foreignId('realized_activity_id')->nullable()->after('status')
                ->constrained('activities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('realized_activity_id');
        });
    }
};
