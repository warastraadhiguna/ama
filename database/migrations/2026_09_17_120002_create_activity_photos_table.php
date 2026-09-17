<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            // docs section 17.3: minimal photo metadata.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy');
            $table->timestamp('captured_at_device');
            $table->timestamp('received_at_server');
            // CameraX is the only source accepted for evidence in V1 (docs
            // section 17.2); the column exists for when gallery import is
            // considered for non-evidence use.
            $table->string('source')->default('CAMERA');
            $table->string('sha256_hash', 64);
            $table->unsignedInteger('file_size');
            $table->string('mime_type');
            $table->string('storage_path');
            // Real evaluation is Milestone G; this just records the field.
            $table->string('integrity_status')->default('PENDING');
            $table->timestamps();

            $table->index('sha256_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_photos');
    }
};
