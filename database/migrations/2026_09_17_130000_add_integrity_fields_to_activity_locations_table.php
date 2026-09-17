<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_locations', function (Blueprint $table) {
            // Client-reported (docs section 19.2, Layer 1) — never trusted
            // on its own (docs section 43), only ever used as one input
            // into the server-side evaluation below.
            $table->boolean('is_mock_location')->default(false)->after('provider');
            // docs section 19.7 (Layer 6): TRUSTED/SUSPICIOUS/REJECTED,
            // the server's own evaluation, not the raw client flag above.
            $table->string('integrity_status')->default('TRUSTED')->after('received_at_server');
            $table->json('anomaly_reasons')->nullable()->after('integrity_status');
        });
    }

    public function down(): void
    {
        Schema::table('activity_locations', function (Blueprint $table) {
            $table->dropColumn(['is_mock_location', 'integrity_status', 'anomaly_reasons']);
        });
    }
};
