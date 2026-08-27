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
        Schema::create('dsr_material_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_material_line_id')->constrained('daily_site_report_material_lines', 'id', 'dsr_mat_rec_line_fk')->restrictOnDelete();
            $table->foreignUuid('inventory_stock_movement_id')->nullable()->constrained('inventory_stock_movements', 'id', 'dsr_mat_rec_move_fk')->restrictOnDelete();
            $table->foreignUuid('material_requisition_line_id')->nullable()->constrained('material_requisition_lines', 'id', 'dsr_mat_rec_req_line_fk')->restrictOnDelete();
            $table->string('type', 30);
            $table->decimal('allocated_quantity', 20, 4);
            $table->string('source_key', 160);
            $table->text('reason');
            $table->foreignUuid('reconciled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reconciled_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'source_key'], 'dsr_mat_rec_source_uq');
            $table->index(['tenant_id', 'daily_site_report_material_line_id'], 'dsr_mat_rec_line_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dsr_material_reconciliations');
    }
};
