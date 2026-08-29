<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsr_expense_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('daily_site_report_cost_line_id');
            $table->uuid('expense_line_id');
            $table->uuid('reconciled_by')->nullable();
            $table->timestamp('reconciled_at');
            $table->text('reason');
            $table->timestamps();

            $table->foreign('tenant_id', 'dsr_expense_rec_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('daily_site_report_cost_line_id', 'dsr_expense_rec_dsr_line_fk')->references('id')->on('daily_site_report_cost_lines')->restrictOnDelete();
            $table->foreign('expense_line_id', 'dsr_expense_rec_exp_line_fk')->references('id')->on('expense_lines')->restrictOnDelete();
            $table->foreign('reconciled_by', 'dsr_expense_rec_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique('daily_site_report_cost_line_id', 'dsr_expense_rec_dsr_uq');
            $table->unique('expense_line_id', 'dsr_expense_rec_expense_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsr_expense_reconciliations');
    }
};
