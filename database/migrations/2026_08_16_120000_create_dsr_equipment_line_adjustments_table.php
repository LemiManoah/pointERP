<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsr_equipment_line_adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('daily_site_report_correction_id');
            $table->uuid('daily_site_report_equipment_line_id');
            $table->uuid('equipment_id');
            $table->decimal('working_hours_delta', 12, 4)->default(0);
            $table->decimal('idle_hours_delta', 12, 4)->default(0);
            $table->decimal('fuel_quantity_delta', 18, 4)->default(0);
            $table->text('reason');
            $table->uuid('equipment_usage_log_id')->nullable();
            $table->uuid('equipment_fuel_transaction_id')->nullable();
            $table->uuid('approved_by');
            $table->dateTime('approved_at');
            $table->timestamps();

            $table->foreign('tenant_id', 'dsr_eq_adj_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'dsr_eq_adj_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('daily_site_report_correction_id', 'dsr_eq_adj_corr_fk')->references('id')->on('daily_site_report_corrections')->restrictOnDelete();
            $table->foreign('daily_site_report_equipment_line_id', 'dsr_eq_adj_line_fk')->references('id')->on('daily_site_report_equipment_lines')->restrictOnDelete();
            $table->foreign('equipment_id', 'dsr_eq_adj_asset_fk')->references('id')->on('equipment')->restrictOnDelete();
            $table->foreign('equipment_usage_log_id', 'dsr_eq_adj_usage_fk')->references('id')->on('equipment_usage_logs')->restrictOnDelete();
            $table->foreign('equipment_fuel_transaction_id', 'dsr_eq_adj_fuel_fk')->references('id')->on('equipment_fuel_transactions')->restrictOnDelete();
            $table->foreign('approved_by', 'dsr_eq_adj_approver_fk')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['daily_site_report_correction_id', 'daily_site_report_equipment_line_id'], 'dsr_eq_adj_corr_line_uq');
            $table->index(['tenant_id', 'equipment_id', 'approved_at'], 'dsr_eq_adj_asset_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsr_equipment_line_adjustments');
    }
};
