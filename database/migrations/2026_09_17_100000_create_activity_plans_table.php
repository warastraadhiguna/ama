<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            // Free text (matches the legacy app's "Lokasi" field): precise GPS
            // only exists once the plan is realized (docs section 19), a plan
            // is just a planned description of where the visit will happen.
            $table->string('location');
            $table->date('planned_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('PLANNED');
            $table->timestamps();

            $table->index(['creator_id', 'status']);
        });

        Schema::create('activity_plan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['activity_plan_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_plan_products');
        Schema::dropIfExists('activity_plans');
    }
};
