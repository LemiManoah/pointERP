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
        Schema::create('pos_sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('pos_sale_id');
            $table->uuid('inventory_item_id');
            $table->uuid('unit_of_measure_id');
            $table->uuid('inventory_item_price_id')->nullable();
            $table->decimal('quantity', 20, 4);
            $table->decimal('conversion_multiplier', 20, 10);
            $table->decimal('stock_quantity', 20, 4);
            $table->decimal('unit_price', 20, 4);
            $table->decimal('discount_amount', 20, 4)->default(0);
            $table->decimal('line_total', 20, 4);
            $table->string('item_code_snapshot', 80);
            $table->string('item_name_snapshot', 180);
            $table->string('unit_symbol_snapshot', 40);
            $table->string('price_list_snapshot', 100);
            $table->text('price_override_reason')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'pos_line_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('pos_sale_id', 'pos_line_sale_fk')->references('id')->on('pos_sales')->cascadeOnDelete();
            $table->foreign('inventory_item_id', 'pos_line_item_fk')->references('id')->on('inventory_items')->restrictOnDelete();
            $table->foreign('unit_of_measure_id', 'pos_line_unit_fk')->references('id')->on('unit_of_measures')->restrictOnDelete();
            $table->foreign('inventory_item_price_id', 'pos_line_price_fk')->references('id')->on('inventory_item_prices')->nullOnDelete();
            $table->index(['tenant_id', 'inventory_item_id'], 'pos_line_item_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sale_lines');
    }
};
