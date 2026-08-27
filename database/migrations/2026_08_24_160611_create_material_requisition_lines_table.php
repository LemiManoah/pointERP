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
        Schema::create('material_requisition_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('material_requisition_id')->constrained('material_requisitions')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->nullable()->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->foreignUuid('project_activity_id')->nullable()->constrained('project_activities')->restrictOnDelete();
            $table->string('item_code_snapshot', 80)->nullable();
            $table->string('item_name_snapshot');
            $table->string('unit_code_snapshot', 50);
            $table->string('unit_symbol_snapshot', 30)->nullable();
            $table->decimal('requested_quantity', 20, 4);
            $table->decimal('conversion_multiplier', 20, 10);
            $table->decimal('stock_quantity', 20, 4);
            $table->decimal('approved_quantity', 20, 4)->default(0);
            $table->decimal('issued_quantity', 20, 4)->default(0);
            $table->decimal('returned_quantity', 20, 4)->default(0);
            $table->string('purpose', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'material_requisition_id'], 'mat_req_line_req_idx');
            $table->index(['tenant_id', 'inventory_item_id'], 'mat_req_line_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requisition_lines');
    }
};
