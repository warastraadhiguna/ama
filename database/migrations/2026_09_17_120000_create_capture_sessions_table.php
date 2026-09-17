<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            // Client-generated (docs section 19.6/22: idempotency key), so a
            // retried request for the same session doesn't create a
            // duplicate — the app can safely retry location/photo uploads.
            $table->uuid('client_uuid');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_sessions');
    }
};
