<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expected_daily_site_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->timestamp('deadline_at');
            $table->string('status')->default('expected');
            $table->foreignUuid('daily_site_report_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'site_id', 'report_date'], 'expected_dsr_tenant_site_date_unique');
            $table->index(['tenant_id', 'project_id', 'status'], 'expected_dsr_tenant_project_status_index');
            $table->index(['tenant_id', 'deadline_at', 'status'], 'expected_dsr_tenant_deadline_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expected_daily_site_reports');
    }
};
