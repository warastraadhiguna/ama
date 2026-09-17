<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Not a rigid enum: docs section 27 lists "Notification
            // configuration" as master data the server could eventually
            // manage, so the set of types is meant to stay open-ended
            // rather than hardcoded. See docs section 28 for the starter
            // list (PLAN_TOMORROW, PLAN_TODAY, PLAN_OVERDUE,
            // ACTIVITY_COMPLETED, ACTIVITY_NEEDS_REVIEW, ADMIN_ANNOUNCEMENT).
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
