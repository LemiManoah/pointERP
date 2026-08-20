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
        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('inventory_batch_id')->nullable()->constrained('inventory_batches')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->string('status', 20)->default('posted');
            $table->decimal('quantity', 20, 4);
            $table->decimal('original_quantity', 20, 4);
            $table->foreignUuid('original_unit_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->decimal('conversion_multiplier', 24, 10)->default(1);
            $table->string('source_type', 100)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_key', 160);
            $table->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('reversal_of_id')->nullable();
            $table->text('reason');
            $table->foreignUuid('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->foreign('reversal_of_id', 'inv_move_reversal_fk')->references('id')->on('inventory_stock_movements')->restrictOnDelete();
            $table->unique(['tenant_id', 'source_key'], 'inv_move_source_uq');
            $table->index(['tenant_id', 'inventory_store_id', 'inventory_item_id', 'posted_at'], 'inv_move_balance_idx');
            $table->index(['tenant_id', 'branch_id', 'movement_type'], 'inv_move_scope_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
