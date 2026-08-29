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
        Schema::create('pos_sale_line_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('pos_sale_line_id');
            $table->uuid('inventory_batch_id')->nullable();
            $table->uuid('inventory_stock_movement_id')->nullable();
            $table->decimal('stock_quantity', 20, 4);
            $table->string('batch_number_snapshot', 100)->nullable();
            $table->date('expires_on_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pos_alloc_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('pos_sale_line_id', 'pos_alloc_line_fk')->references('id')->on('pos_sale_lines')->cascadeOnDelete();
            $table->foreign('inventory_batch_id', 'pos_alloc_batch_fk')->references('id')->on('inventory_batches')->restrictOnDelete();
            $table->foreign('inventory_stock_movement_id', 'pos_alloc_movement_fk')->references('id')->on('inventory_stock_movements')->restrictOnDelete();
            $table->index(['tenant_id', 'inventory_batch_id'], 'pos_alloc_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sale_line_allocations');
    }
};
