<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimate_resource_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('project_estimate_line_id');
            $table->uuid('inventory_item_id')->nullable();
            $table->uuid('unit_of_measure_id')->nullable();
            $table->string('resource_type', 24);
            $table->string('name', 220);
            $table->decimal('quantity_per_work_unit', 20, 6);
            $table->decimal('estimated_unit_cost', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'est_res_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('project_estimate_line_id', 'est_res_est_line_fk')->references('id')->on('project_estimate_lines')->cascadeOnDelete();
            $table->foreign('inventory_item_id', 'est_res_item_fk')->references('id')->on('inventory_items')->restrictOnDelete();
            $table->foreign('unit_of_measure_id', 'est_res_unit_fk')->references('id')->on('unit_of_measures')->restrictOnDelete();
            $table->index(['tenant_id', 'project_estimate_line_id'], 'est_res_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_resource_lines');
    }
};
