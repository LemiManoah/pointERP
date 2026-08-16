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
        Schema::create('equipment_maintenance_work_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('equipment_maintenance_schedule_id')->nullable()->constrained('equipment_maintenance_schedules', 'id', 'eq_maint_wo_schedule_fk')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('maintenance_type');
            $table->string('priority')->default('normal');
            $table->text('description');
            $table->string('status')->default('planned');
            $table->string('prior_equipment_status')->nullable();
            $table->dateTime('reported_at');
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->decimal('opening_meter_reading', 18, 4)->nullable();
            $table->decimal('closing_meter_reading', 18, 4)->nullable();
            $table->foreignUuid('provider_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('provider_name')->nullable();
            $table->decimal('downtime_hours', 16, 4)->nullable();
            $table->decimal('labour_cost', 20, 4)->nullable();
            $table->decimal('parts_cost', 20, 4)->nullable();
            $table->decimal('other_cost', 20, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('findings')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->date('next_service_date')->nullable();
            $table->decimal('next_service_reading', 18, 4)->nullable();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('supervised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['tenant_id', 'reference'], 'eq_maint_wo_reference_uq');
            $table->index(['tenant_id', 'branch_id', 'status'], 'eq_maint_wo_scope_status_idx');
            $table->index(['equipment_id', 'reported_at'], 'eq_maint_wo_asset_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_work_orders');
    }
};
