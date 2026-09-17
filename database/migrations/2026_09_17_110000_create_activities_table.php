<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            // Free text for now; Milestone F (Evidence) adds real GPS via
            // activity_locations, and Milestone G evaluates its integrity.
            $table->string('location');
            $table->text('notes')->nullable();
            // docs section 23: DRAFT/SUBMITTED/SYNCED/VERIFIED/REJECTED. Only
            // DRAFT is reachable from this milestone's endpoints — SUBMITTED
            // onward depends on the evidence-completion flow (Milestone F).
            $table->string('status')->default('DRAFT');
            $table->timestamps();

            $table->index(['creator_id', 'status']);
        });

        Schema::create('activity_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['activity_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_products');
        Schema::dropIfExists('activities');
    }
};
