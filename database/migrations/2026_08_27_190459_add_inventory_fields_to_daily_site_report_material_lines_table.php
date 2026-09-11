<?php

declare(strict_types=1);

use App\Enums\DsrMaterialSource;
use App\Enums\DsrMaterialUsageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_site_report_material_lines', function (Blueprint $table): void {
            $table->foreignUuid('inventory_item_id')->nullable()->after('daily_site_report_id')->constrained('inventory_items', 'id', 'dsr_mat_item_fk')->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->nullable()->after('inventory_item_id')->constrained('inventory_stores', 'id', 'dsr_mat_store_fk')->restrictOnDelete();
            $table->foreignUuid('inventory_batch_id')->nullable()->after('inventory_store_id')->constrained('inventory_batches', 'id', 'dsr_mat_batch_fk')->restrictOnDelete();
            $table->foreignUuid('unit_of_measure_id')->nullable()->after('inventory_batch_id')->constrained('unit_of_measures', 'id', 'dsr_mat_unit_fk')->restrictOnDelete();
            $table->decimal('conversion_multiplier', 24, 10)->nullable()->after('unit_of_measure_id');
            $table->decimal('stock_unit_quantity', 20, 4)->nullable()->after('conversion_multiplier');
            $table->string('material_source', 24)->default(DsrMaterialSource::SiteStore->value)->after('stock_unit_quantity');
            $table->string('material_usage_status', 24)->default(DsrMaterialUsageStatus::Pending->value)->after('material_source');
            $table->foreignUuid('inventory_stock_movement_id')->nullable()->after('material_usage_status')->constrained('inventory_stock_movements', 'id', 'dsr_mat_movement_fk')->restrictOnDelete();
            $table->text('external_material_reason')->nullable()->after('inventory_stock_movement_id');
            $table->foreignUuid('posted_by')->nullable()->after('external_material_reason')->constrained('users', 'id', 'dsr_mat_actor_fk')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->index(['tenant_id', 'material_usage_status'], 'dsr_mat_usage_status_idx');
            $table->index(['tenant_id', 'branch_id', 'material_usage_status'], 'dsr_mat_scope_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daily_site_report_material_lines', function (Blueprint $table): void {
            $table->dropIndex('dsr_mat_usage_status_idx');
            $table->dropIndex('dsr_mat_scope_status_idx');
            $table->dropForeign('dsr_mat_actor_fk');
            $table->dropForeign('dsr_mat_movement_fk');
            $table->dropForeign('dsr_mat_unit_fk');
            $table->dropForeign('dsr_mat_batch_fk');
            $table->dropForeign('dsr_mat_store_fk');
            $table->dropForeign('dsr_mat_item_fk');
            $table->dropColumn(['posted_by', 'inventory_stock_movement_id', 'unit_of_measure_id', 'inventory_batch_id', 'inventory_store_id', 'inventory_item_id']);
            $table->dropColumn(['conversion_multiplier', 'stock_unit_quantity', 'material_source', 'material_usage_status', 'external_material_reason', 'posted_at']);
        });
    }
};
