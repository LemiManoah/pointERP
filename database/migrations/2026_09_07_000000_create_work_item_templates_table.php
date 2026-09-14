<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 80)->nullable();
            $table->string('category', 120)->index();
            $table->string('name', 220);
            $table->uuid('unit_of_measure_id');
            $table->decimal('default_selling_rate', 20, 4)->nullable();
            $table->decimal('default_unit_cost', 20, 4)->nullable();
            $table->text('specifications')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'wit_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('unit_of_measure_id', 'wit_unit_fk')->references('id')->on('unit_of_measures')->restrictOnDelete();
            $table->foreign('created_by', 'wit_created_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'wit_updated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'category'], 'wit_tenant_category_idx');
        });

        Schema::create('work_item_resource_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('work_item_template_id');
            $table->string('resource_type', 24);
            $table->uuid('inventory_item_id')->nullable();
            $table->uuid('unit_of_measure_id')->nullable();
            $table->uuid('equipment_category_id')->nullable();
            $table->uuid('workforce_trade_id')->nullable();
            $table->uuid('subcontractor_id')->nullable();
            $table->string('name', 220);
            $table->decimal('quantity_per_work_unit', 20, 6);
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'wirt_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('work_item_template_id', 'wirt_template_fk')->references('id')->on('work_item_templates')->cascadeOnDelete();
            $table->foreign('inventory_item_id', 'wirt_item_fk')->references('id')->on('inventory_items')->nullOnDelete();
            $table->foreign('unit_of_measure_id', 'wirt_unit_fk')->references('id')->on('unit_of_measures')->nullOnDelete();
            $table->foreign('equipment_category_id', 'wirt_equipment_category_fk')->references('id')->on('equipment_categories')->nullOnDelete();
            $table->foreign('subcontractor_id', 'wirt_subcontractor_fk')->references('id')->on('customers')->nullOnDelete();
            $table->index(['tenant_id', 'work_item_template_id'], 'wirt_tenant_template_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_resource_templates');
        Schema::dropIfExists('work_item_templates');
    }
};
