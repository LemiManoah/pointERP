<?php

declare(strict_types=1);

use App\Enums\InventoryBatchStatus;
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
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->nullable()->constrained('inventory_stores')->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 30)->default(InventoryBatchStatus::Available->value);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'inventory_item_id', 'batch_number'], 'inv_batch_item_number_uq');
            $table->index(['tenant_id', 'inventory_item_id', 'status'], 'inv_batch_scope_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
