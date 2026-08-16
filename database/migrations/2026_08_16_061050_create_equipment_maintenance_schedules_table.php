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
        Schema::create('equipment_maintenance_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('maintenance_type');
            $table->string('name');
            $table->string('basis');
            $table->unsignedInteger('interval_days')->nullable();
            $table->decimal('interval_meter_units', 18, 4)->nullable();
            $table->date('last_service_date')->nullable();
            $table->decimal('last_service_reading', 18, 4)->nullable();
            $table->date('next_due_date')->nullable();
            $table->decimal('next_due_reading', 18, 4)->nullable();
            $table->unsignedInteger('warning_days')->default(14);
            $table->decimal('warning_meter_units', 18, 4)->default(50);
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'equipment_id', 'name'], 'eq_maint_schedule_name_uq');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'eq_maint_schedule_scope_idx');
            $table->index(['next_due_date', 'next_due_reading'], 'eq_maint_schedule_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_schedules');
    }
};
