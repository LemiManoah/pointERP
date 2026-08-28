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
        Schema::create('inventory_direct_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('source_company_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->uuid('receipt_key');
            $table->string('reference', 40);
            $table->string('source_reference', 100)->nullable();
            $table->date('received_on');
            $table->string('reason', 40);
            $table->foreignUuid('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_key'], 'direct_receipt_key_unique');
            $table->unique(['tenant_id', 'reference'], 'direct_receipt_ref_unique');
            $table->index(['branch_id', 'received_on'], 'direct_receipt_branch_date_idx');
            $table->index(['inventory_store_id', 'received_on'], 'direct_receipt_store_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_direct_receipts');
    }
};
