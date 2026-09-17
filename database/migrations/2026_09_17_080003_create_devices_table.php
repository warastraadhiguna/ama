<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid')->unique();
            $table->string('app_version');
            $table->string('os_version')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            // Layer 6 (docs section 19.7) risk status, defaults to trusted until evaluated otherwise.
            $table->string('integrity_status')->default('TRUSTED');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
