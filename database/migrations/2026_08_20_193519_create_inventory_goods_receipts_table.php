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
        Schema::create('inventory_goods_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('customers')->restrictOnDelete();
            $table->string('reference', 60);
            $table->string('supplier_reference', 100)->nullable();
            $table->date('received_on');
            $table->char('currency_code', 3);
            $table->decimal('total_amount', 20, 4);
            $table->text('notes')->nullable();
            $table->foreignUuid('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference'], 'inv_receipt_reference_uq');
            $table->index(['tenant_id', 'branch_id', 'received_on'], 'inv_receipt_scope_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_goods_receipts');
    }
};
