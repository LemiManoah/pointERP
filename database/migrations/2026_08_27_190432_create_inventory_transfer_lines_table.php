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
        Schema::create('inventory_transfer_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('unit_of_measure_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->foreignUuid('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('conversion_multiplier', 24, 10)->default(1);
            $table->decimal('stock_quantity', 20, 4);
            $table->string('item_code_snapshot');
            $table->string('item_name_snapshot');
            $table->string('unit_symbol_snapshot', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'inventory_transfer_id'], 'inv_transfer_line_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_lines');
    }
};
