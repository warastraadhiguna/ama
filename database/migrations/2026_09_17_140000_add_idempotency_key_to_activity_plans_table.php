<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_plans', function (Blueprint $table) {
            // docs section 22: retry must be safe/idempotent, driven by a
            // client-generated UUID. Nullable so older rows (pre-dating
            // this column) don't need a backfill, but the API requires it
            // on every create going forward.
            $table->uuid('idempotency_key')->nullable()->after('creator_id');
            $table->unique(['creator_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_plans', function (Blueprint $table) {
            $table->dropUnique(['creator_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
