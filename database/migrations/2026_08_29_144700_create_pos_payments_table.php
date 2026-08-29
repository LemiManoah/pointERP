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
        Schema::create('pos_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('pos_sale_id');
            $table->string('payment_number', 60);
            $table->string('method', 30);
            $table->decimal('amount', 20, 4);
            $table->char('currency_code', 3);
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30);
            $table->uuid('recorded_by');
            $table->timestamp('recorded_at');
            $table->uuid('reversal_of_id')->nullable();
            $table->uuid('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pos_payment_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'pos_payment_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('pos_sale_id', 'pos_payment_sale_fk')->references('id')->on('pos_sales')->restrictOnDelete();
            $table->foreign('currency_code', 'pos_payment_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('recorded_by', 'pos_payment_actor_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reversal_of_id', 'pos_payment_reversal_fk')->references('id')->on('pos_payments')->restrictOnDelete();
            $table->foreign('reversed_by', 'pos_payment_reversed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'payment_number'], 'pos_payment_tenant_number_uq');
            $table->index(['tenant_id', 'branch_id', 'status', 'recorded_at'], 'pos_payment_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
