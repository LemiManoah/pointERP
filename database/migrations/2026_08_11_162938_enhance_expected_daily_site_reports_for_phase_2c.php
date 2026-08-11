<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expected_daily_site_reports', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->after('daily_site_report_id');
            $table->text('excuse_reason')->nullable()->after('escalated_at');
            $table->foreignUuid('marked_by')->nullable()->after('excuse_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable()->after('marked_by');
        });
    }

    public function down(): void
    {
        Schema::table('expected_daily_site_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('marked_by');
            $table->dropColumn(['submitted_at', 'excuse_reason', 'marked_at']);
        });
    }
};
