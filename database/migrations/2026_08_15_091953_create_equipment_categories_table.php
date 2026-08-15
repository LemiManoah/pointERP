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
        Schema::create('equipment_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('default_meter_type')->default('none');
            $table->string('default_capacity_unit', 40)->nullable();
            $table->string('fuel_efficiency_basis')->nullable();
            $table->decimal('expected_fuel_efficiency', 16, 4)->nullable();
            $table->decimal('fuel_tolerance_percent', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'eq_cat_tenant_code_uq');
            $table->unique(['tenant_id', 'name'], 'eq_cat_tenant_name_uq');
            $table->index(['tenant_id', 'is_active'], 'eq_cat_tenant_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_categories');
    }
};
