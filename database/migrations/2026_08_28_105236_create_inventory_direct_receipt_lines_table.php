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
        Schema::create('inventory_direct_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_direct_receipt_id');
            $table->foreignUuid('inventory_item_id');
            $table->foreignUuid('unit_of_measure_id');
            $table->foreignUuid('inventory_batch_id')->nullable();
            $table->foreignUuid('inventory_stock_movement_id');
            $table->string('item_code_snapshot', 80);
            $table->string('item_name_snapshot');
            $table->string('unit_symbol_snapshot', 40)->nullable();
            $table->decimal('quantity', 20, 4);
            $table->decimal('conversion_multiplier', 20, 10);
            $table->decimal('stock_quantity', 20, 4);
            $table->string('batch_number', 100)->nullable();
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();

            $table->foreign('inventory_direct_receipt_id', 'direct_receipt_line_receipt_fk')->references('id')->on('inventory_direct_receipts')->restrictOnDelete();
            $table->foreign('inventory_item_id', 'direct_receipt_line_item_fk')->references('id')->on('inventory_items')->restrictOnDelete();
            $table->foreign('unit_of_measure_id', 'direct_receipt_line_unit_fk')->references('id')->on('unit_of_measures')->restrictOnDelete();
            $table->foreign('inventory_batch_id', 'direct_receipt_line_batch_fk')->references('id')->on('inventory_batches')->restrictOnDelete();
            $table->foreign('inventory_stock_movement_id', 'direct_receipt_line_movement_fk')->references('id')->on('inventory_stock_movements')->restrictOnDelete();
            $table->index(['inventory_direct_receipt_id', 'inventory_item_id'], 'direct_receipt_line_item_idx');
            $table->index('inventory_stock_movement_id', 'direct_receipt_line_movement_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_direct_receipt_lines');
    }
};
