<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('position_id')->nullable()->after('phone')->constrained('positions')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->after('position_id')->constrained('work_locations')->nullOnDelete();
            $table->string('photo_path')->nullable()->after('work_location_id');
            $table->boolean('is_active')->default(true)->after('photo_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('work_location_id');
            $table->dropColumn(['nip', 'phone', 'photo_path', 'is_active', 'last_login_at']);
        });
    }
};
