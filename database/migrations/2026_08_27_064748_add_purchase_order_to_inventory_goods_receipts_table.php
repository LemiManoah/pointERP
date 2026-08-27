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
        Schema::table('inventory_goods_receipts', function (Blueprint $table): void {
            $table->foreignUuid('purchase_order_id')->after('supplier_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->uuid('source_key')->nullable()->unique('inv_receipt_source_uq')->after('purchase_order_id');
            $table->string('inspection_status', 30)->default('accepted')->after('total_amount');
            $table->foreignUuid('verified_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->index(['tenant_id', 'purchase_order_id'], 'inv_receipt_po_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_goods_receipts', function (Blueprint $table): void {
            $table->dropIndex('inv_receipt_po_idx');
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropUnique('inv_receipt_source_uq');
            $table->dropColumn(['source_key', 'inspection_status', 'verified_at']);
        });
    }
};
