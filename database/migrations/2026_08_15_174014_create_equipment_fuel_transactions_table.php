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
        Schema::create('equipment_fuel_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('transacted_at');
            $table->string('transaction_type');
            $table->string('fuel_type');
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 20)->default('litre');
            $table->string('source_type');
            $table->foreignUuid('provider_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('source_name')->nullable();
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('meter_reading', 18, 4)->nullable();
            $table->decimal('tank_level_before', 16, 4)->nullable();
            $table->decimal('tank_level_after', 16, 4)->nullable();
            $table->boolean('is_full_tank')->default(false);
            $table->foreignUuid('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('received_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('voucher_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('daily_site_report_equipment_line_id')->nullable();
            $table->string('exception_status')->default('not_evaluated');
            $table->text('exception_reason')->nullable();
            $table->string('status')->default('submitted');
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('equipment_fuel_transactions')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignUuid('submitted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('daily_site_report_equipment_line_id', 'eq_fuel_dsr_line_fk')
                ->references('id')
                ->on('daily_site_report_equipment_lines')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'branch_id', 'status'], 'eq_fuel_scope_status_idx');
            $table->index(['equipment_id', 'transacted_at'], 'eq_fuel_asset_time_idx');
            $table->index(['project_id', 'site_id', 'transacted_at'], 'eq_fuel_work_time_idx');
            $table->unique('daily_site_report_equipment_line_id', 'eq_fuel_dsr_line_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_fuel_transactions');
    }
};
