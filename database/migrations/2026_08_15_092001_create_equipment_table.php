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
        Schema::create('equipment', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_category_id')->constrained()->restrictOnDelete();
            $table->string('asset_code', 60);
            $table->string('name', 160);
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('ownership_type');
            $table->foreignUuid('owner_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->decimal('capacity_value', 16, 4)->nullable();
            $table->string('capacity_unit', 40)->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('acquisition_amount', 20, 4)->nullable();
            $table->char('acquisition_currency_code', 3)->nullable();
            $table->decimal('hire_rate', 20, 4)->nullable();
            $table->string('hire_rate_basis')->nullable();
            $table->foreignUuid('default_location_id')->nullable()->constrained('equipment_locations')->nullOnDelete();
            $table->string('meter_type');
            $table->decimal('starting_meter_reading', 18, 4)->nullable();
            $table->date('starting_meter_date')->nullable();
            $table->string('fuel_efficiency_basis')->nullable();
            $table->decimal('expected_fuel_efficiency', 16, 4)->nullable();
            $table->decimal('fuel_tolerance_percent', 8, 4)->nullable();
            $table->decimal('tank_capacity', 16, 4)->nullable();
            $table->string('current_status')->default('available');
            $table->foreignUuid('current_location_id')->nullable()->constrained('equipment_locations')->nullOnDelete();
            $table->foreignUuid('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('current_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignUuid('current_custodian_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->decimal('current_meter_reading', 18, 4)->nullable();
            $table->dateTime('current_meter_read_at')->nullable();
            $table->text('condition_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'asset_code'], 'eq_tenant_asset_code_uq');
            $table->unique(['tenant_id', 'serial_number'], 'eq_tenant_serial_uq');
            $table->unique(['tenant_id', 'registration_number'], 'eq_tenant_reg_uq');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'eq_scope_active_idx');
            $table->index(['tenant_id', 'current_status'], 'eq_tenant_status_idx');
            $table->foreign('acquisition_currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
