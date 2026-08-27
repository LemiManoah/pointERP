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
        Schema::create('inventory_reconciliation_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_reconciliation_id')->constrained('inventory_reconciliations', 'id', 'inv_rec_line_header_fk')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->decimal('system_quantity', 20, 4);
            $table->decimal('counted_quantity', 20, 4);
            $table->decimal('variance_quantity', 20, 4);
            $table->string('item_code_snapshot');
            $table->string('item_name_snapshot');
            $table->string('unit_symbol_snapshot', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_reconciliation_id'], 'inv_reconcile_line_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reconciliation_lines');
    }
};
