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
        Schema::create('pos_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('inventory_store_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('inventory_price_tier_id');
            $table->string('sale_number', 50);
            $table->uuid('checkout_key');
            $table->string('status', 30);
            $table->char('currency_code', 3);
            $table->decimal('subtotal', 20, 4);
            $table->decimal('discount_total', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4);
            $table->decimal('amount_paid', 20, 4)->default(0);
            $table->decimal('balance_due', 20, 4)->default(0);
            $table->string('payment_status', 30)->default('unpaid');
            $table->text('notes')->nullable();
            $table->uuid('sold_by');
            $table->uuid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pos_sale_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'pos_sale_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('inventory_store_id', 'pos_sale_store_fk')->references('id')->on('inventory_stores')->restrictOnDelete();
            $table->foreign('customer_id', 'pos_sale_customer_fk')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('inventory_price_tier_id', 'pos_sale_tier_fk')->references('id')->on('inventory_price_tiers')->restrictOnDelete();
            $table->foreign('currency_code', 'pos_sale_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('sold_by', 'pos_sale_sold_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completed_by', 'pos_sale_completed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by', 'pos_sale_cancelled_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'sale_number'], 'pos_sale_tenant_number_uq');
            $table->unique(['tenant_id', 'checkout_key'], 'pos_sale_tenant_checkout_uq');
            $table->index(['tenant_id', 'branch_id', 'status', 'completed_at'], 'pos_sale_scope_status_idx');
            $table->index(['tenant_id', 'branch_id', 'payment_status'], 'pos_sale_payment_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
