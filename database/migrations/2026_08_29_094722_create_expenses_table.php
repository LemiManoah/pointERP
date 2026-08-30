<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('daily_site_report_id')->nullable();
            $table->string('expense_number', 40);
            $table->date('expense_date');
            $table->string('payee_type', 20);
            $table->uuid('customer_id')->nullable();
            $table->uuid('staff_id')->nullable();
            $table->string('payee_name_snapshot', 180);
            $table->char('currency_code', 3);
            $table->char('base_currency_code', 3);
            $table->uuid('exchange_rate_id')->nullable();
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->decimal('subtotal', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->decimal('base_currency_total', 20, 4)->default(0);
            $table->text('description')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('status', 20)->default('draft');
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->uuid('corrects_expense_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'expense_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'expense_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('daily_site_report_id', 'expense_dsr_fk')->references('id')->on('daily_site_reports')->restrictOnDelete();
            $table->foreign('customer_id', 'expense_customer_fk')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('staff_id', 'expense_staff_fk')->references('id')->on('staff')->nullOnDelete();
            $table->foreign('currency_code', 'expense_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('base_currency_code', 'expense_base_currency_fk')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('exchange_rate_id', 'expense_rate_fk')->references('id')->on('exchange_rates')->nullOnDelete();
            $table->foreign('corrects_expense_id', 'expense_corrects_fk')->references('id')->on('expenses')->nullOnDelete();
            $table->foreign('submitted_by', 'expense_submitted_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'expense_approved_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by', 'expense_rejected_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by', 'expense_cancelled_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'expense_created_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'expense_updated_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'expense_number'], 'expense_tenant_number_uq');
            $table->index(['tenant_id', 'branch_id', 'status', 'expense_date'], 'expense_scope_status_idx');
            $table->index(['tenant_id', 'daily_site_report_id'], 'expense_dsr_idx');
            $table->index(['tenant_id', 'customer_id', 'reference'], 'expense_supplier_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
