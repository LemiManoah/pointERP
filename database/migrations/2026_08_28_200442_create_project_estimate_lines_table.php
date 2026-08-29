<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_estimate_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_estimate_id');
            $table->uuid('site_id')->nullable();
            $table->uuid('unit_of_measure_id');
            $table->uuid('work_item_key');
            $table->string('boq_reference', 80)->nullable();
            $table->string('code', 80)->nullable();
            $table->string('name', 220);
            $table->decimal('planned_quantity', 20, 4);
            $table->decimal('selling_rate', 20, 4)->nullable();
            $table->decimal('estimated_unit_cost', 20, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'proj_est_line_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('project_estimate_id', 'proj_est_line_estimate_fk')->references('id')->on('project_estimates')->cascadeOnDelete();
            $table->foreign('site_id', 'proj_est_line_site_fk')->references('id')->on('sites')->nullOnDelete();
            $table->foreign('unit_of_measure_id', 'proj_est_line_unit_fk')->references('id')->on('unit_of_measures')->restrictOnDelete();
            $table->unique(['project_estimate_id', 'work_item_key'], 'proj_est_line_work_key_uq');
            $table->index(['tenant_id', 'project_estimate_id'], 'proj_est_line_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_estimate_lines');
    }
};
