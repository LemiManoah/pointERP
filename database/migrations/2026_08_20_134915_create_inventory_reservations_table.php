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
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->string('source_type', 100);
            $table->uuid('source_id');
            $table->decimal('reserved_quantity', 20, 4);
            $table->decimal('issued_quantity', 20, 4)->default(0);
            $table->decimal('released_quantity', 20, 4)->default(0);
            $table->string('status', 20)->default('active');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_type', 'source_id', 'inventory_item_id'], 'inv_res_source_item_uq');
            $table->index(['tenant_id', 'inventory_store_id', 'inventory_item_id', 'status'], 'inv_res_balance_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
