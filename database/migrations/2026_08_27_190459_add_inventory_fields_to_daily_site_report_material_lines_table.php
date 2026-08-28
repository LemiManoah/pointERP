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
        Schema::table('daily_site_report_material_lines', function (Blueprint $table): void {
            $table->foreignUuid('inventory_item_id')->nullable()->after('daily_site_report_id')->constrained('inventory_items', 'id', 'dsr_mat_item_fk')->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->nullable()->after('inventory_item_id')->constrained('inventory_stores', 'id', 'dsr_mat_store_fk')->restrictOnDelete();
            $table->foreignUuid('unit_of_measure_id')->nullable()->after('inventory_store_id')->constrained('unit_of_measures', 'id', 'dsr_mat_unit_fk')->restrictOnDelete();
            $table->decimal('conversion_multiplier', 24, 10)->nullable()->after('unit_of_measure_id');
            $table->decimal('stock_unit_quantity', 20, 4)->nullable()->after('conversion_multiplier');
            $table->string('inventory_reconciliation_status', 24)->default('not_linked')->after('stock_unit_quantity');
            $table->text('external_material_reason')->nullable()->after('inventory_reconciliation_status');
            $table->foreignUuid('reconciled_by')->nullable()->after('external_material_reason')->constrained('users', 'id', 'dsr_mat_actor_fk')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable()->after('reconciled_by');
            $table->index(['tenant_id', 'inventory_reconciliation_status'], 'dsr_mat_reconcile_status_idx');
            $table->index(['tenant_id', 'branch_id', 'inventory_reconciliation_status'], 'dsr_mat_scope_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_site_report_material_lines', function (Blueprint $table): void {
            $table->dropIndex('dsr_mat_reconcile_status_idx');
            $table->dropForeign('dsr_mat_actor_fk');
            $table->dropForeign('dsr_mat_unit_fk');
            $table->dropForeign('dsr_mat_store_fk');
            $table->dropForeign('dsr_mat_item_fk');
            $table->dropIndex('dsr_mat_scope_status_idx');
            $table->dropColumn(['reconciled_by', 'unit_of_measure_id', 'inventory_store_id', 'inventory_item_id']);
            $table->dropColumn(['conversion_multiplier', 'stock_unit_quantity', 'inventory_reconciliation_status', 'external_material_reason', 'reconciled_at']);
        });
    }
};
