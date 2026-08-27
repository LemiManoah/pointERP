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
        Schema::table('inventory_goods_receipt_lines', function (Blueprint $table): void {
            $table->foreignUuid('purchase_order_line_id')->after('inventory_goods_receipt_id')->constrained('purchase_order_lines')->restrictOnDelete();
            $table->decimal('accepted_quantity', 20, 4)->default(0)->after('quantity');
            $table->decimal('rejected_quantity', 20, 4)->default(0)->after('accepted_quantity');
            $table->text('rejection_reason')->nullable()->after('rejected_quantity');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'inv_receipt_line_po_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_goods_receipt_lines', function (Blueprint $table): void {
            $table->dropIndex('inv_receipt_line_po_idx');
            $table->dropConstrainedForeignId('purchase_order_line_id');
            $table->dropColumn(['accepted_quantity', 'rejected_quantity', 'rejection_reason']);
        });
    }
};
