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
        Schema::create('inventory_goods_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('inventory_goods_receipt_id');
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->uuid('inventory_stock_movement_id')->nullable();
            $table->decimal('quantity', 20, 4);
            $table->foreignUuid('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->decimal('stock_quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('line_total', 20, 4);
            $table->string('batch_number', 100)->nullable();
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();
            $table->foreign('inventory_goods_receipt_id', 'inv_receipt_line_receipt_fk')->references('id')->on('inventory_goods_receipts')->restrictOnDelete();
            $table->foreign('inventory_stock_movement_id', 'inv_receipt_line_movement_fk')->references('id')->on('inventory_stock_movements')->restrictOnDelete();
            $table->index(['tenant_id', 'inventory_goods_receipt_id'], 'inv_receipt_line_header_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_goods_receipt_lines');
    }
};
