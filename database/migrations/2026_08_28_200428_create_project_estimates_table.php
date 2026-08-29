<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_estimates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('project_id');
            $table->unsignedSmallInteger('version_number');
            $table->string('title', 160);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_baseline')->default(false);
            $table->text('notes')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'proj_est_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'proj_est_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('project_id', 'proj_est_project_fk')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('currency_code', 'proj_est_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('approved_by', 'proj_est_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'proj_est_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'proj_est_updated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['project_id', 'version_number'], 'proj_est_project_version_uq');
            $table->index(['tenant_id', 'project_id', 'status'], 'proj_est_scope_status_idx');
            $table->index(['project_id', 'is_baseline'], 'proj_est_baseline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_estimates');
    }
};
