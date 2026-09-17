<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->after('creator_id');
            $table->unique(['creator_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['creator_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
