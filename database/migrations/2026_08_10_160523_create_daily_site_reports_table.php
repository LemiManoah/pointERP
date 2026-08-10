<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->string('reference');
            $table->string('weather')->nullable();
            $table->string('site_conditions')->nullable();
            $table->text('work_summary')->nullable();
            $table->text('delay_summary')->nullable();
            $table->text('visitor_summary')->nullable();
            $table->text('hse_notes')->nullable();
            $table->text('environment_notes')->nullable();
            $table->text('social_notes')->nullable();
            $table->decimal('completion_percent', 8, 4)->nullable();
            $table->decimal('output_value', 20, 4)->nullable();
            $table->decimal('input_cost', 20, 4)->nullable();
            $table->decimal('profit_loss', 20, 4)->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('expected_at')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'site_id', 'report_date'], 'dsr_tenant_site_date_unique');
            $table->unique(['tenant_id', 'reference'], 'dsr_tenant_reference_unique');
            $table->index(['tenant_id', 'project_id', 'status'], 'dsr_tenant_project_status_index');
            $table->index(['tenant_id', 'site_id', 'report_date'], 'dsr_tenant_site_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_reports');
    }
};
