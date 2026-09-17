<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_session_id')->constrained()->cascadeOnDelete();
            // docs section 19.4 (Layer 3 GPS metadata).
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy');
            $table->float('altitude')->nullable();
            $table->float('speed')->nullable();
            $table->float('bearing')->nullable();
            $table->string('provider')->nullable();
            $table->timestamp('captured_at_device');
            $table->timestamp('received_at_server');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_locations');
    }
};
