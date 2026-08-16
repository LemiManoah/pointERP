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
        Schema::create('equipment_maintenance_part_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_maintenance_work_order_id')->constrained('equipment_maintenance_work_orders', 'id', 'eq_maint_part_work_order_fk')->cascadeOnDelete();
            $table->string('part_code')->nullable();
            $table->string('part_name');
            $table->decimal('quantity', 16, 4);
            $table->string('unit', 30);
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->foreignUuid('provider_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('provider_name')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['tenant_id', 'equipment_maintenance_work_order_id'], 'eq_maint_part_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_part_lines');
    }
};
