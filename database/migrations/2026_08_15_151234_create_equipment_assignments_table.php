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
        Schema::create('equipment_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_location_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('custodian_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('external_custodian_name')->nullable();
            $table->string('external_custodian_employer')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('expected_return_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->decimal('handover_meter_reading', 18, 4)->nullable();
            $table->decimal('return_meter_reading', 18, 4)->nullable();
            $table->text('handover_condition');
            $table->text('return_condition')->nullable();
            $table->text('assignment_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->string('status')->default('active');
            $table->foreignUuid('handed_over_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('accepted_return_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('return_location_id')->nullable()->constrained('equipment_locations')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status'], 'eq_assign_scope_status_idx');
            $table->index(['equipment_id', 'status'], 'eq_assign_asset_status_idx');
            $table->index(['site_id', 'status'], 'eq_assign_site_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_assignments');
    }
};
