<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_site_reports', function (Blueprint $table): void {
            $table->string('labour_attendance_status', 40)->nullable()->after('profit_loss');
            $table->json('labour_attendance_snapshot')->nullable()->after('labour_attendance_status');
            $table->timestamp('labour_attendance_checked_at')->nullable()->after('labour_attendance_snapshot');
            $table->text('labour_attendance_override_reason')->nullable()->after('labour_attendance_checked_at');
            $table->foreignUuid('labour_attendance_override_by')->nullable()->after('labour_attendance_override_reason');
            $table->foreign('labour_attendance_override_by', 'dsr_labour_override_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_site_reports', function (Blueprint $table): void {
            $table->dropForeign('dsr_labour_override_user_fk');
            $table->dropColumn([
                'labour_attendance_status',
                'labour_attendance_snapshot',
                'labour_attendance_checked_at',
                'labour_attendance_override_reason',
                'labour_attendance_override_by',
            ]);
        });
    }
};
