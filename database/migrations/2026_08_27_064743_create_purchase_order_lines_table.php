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
        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->string('item_code_snapshot', 80)->nullable();
            $table->string('item_name_snapshot');
            $table->string('unit_code_snapshot', 50);
            $table->string('unit_symbol_snapshot', 30)->nullable();
            $table->decimal('ordered_quantity', 20, 4);
            $table->decimal('conversion_multiplier', 20, 10);
            $table->decimal('stock_quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->string('price_source', 30)->default('recorded_cost');
            $table->decimal('line_amount', 20, 4);
            $table->decimal('accepted_quantity', 20, 4)->default(0);
            $table->decimal('rejected_quantity', 20, 4)->default(0);
            $table->decimal('cancelled_quantity', 20, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'po_line_header_idx');
            $table->index(['inventory_item_id', 'purchase_order_id'], 'po_line_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
