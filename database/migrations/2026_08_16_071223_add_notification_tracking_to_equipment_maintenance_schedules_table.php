<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('equipment_maintenance_schedules', function (Blueprint $table): void {
            $table->string('last_notified_status')->nullable()->after('is_active');
            $table->dateTime('last_notified_at')->nullable()->after('last_notified_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_maintenance_schedules', function (Blueprint $table): void {
            $table->dropColumn(['last_notified_status', 'last_notified_at']);
        });
    }
};
