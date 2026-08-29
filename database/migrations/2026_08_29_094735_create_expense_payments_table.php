<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('expense_id');
            $table->string('payment_number', 40);
            $table->dateTime('paid_at');
            $table->decimal('amount', 20, 4);
            $table->char('currency_code', 3);
            $table->decimal('base_currency_amount', 20, 4);
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->string('payment_method', 30);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('recorded');
            $table->uuid('reverses_payment_id')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->uuid('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'expense_payment_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'expense_payment_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('expense_id', 'expense_payment_expense_fk')->references('id')->on('expenses')->restrictOnDelete();
            $table->foreign('currency_code', 'expense_payment_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('reverses_payment_id', 'expense_payment_reverses_fk')->references('id')->on('expense_payments')->nullOnDelete();
            $table->foreign('recorded_by', 'expense_payment_recorded_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_by', 'expense_payment_reversed_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'payment_number'], 'expense_payment_number_uq');
            $table->index(['tenant_id', 'expense_id', 'status'], 'expense_payment_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
