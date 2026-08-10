<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_equipment_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->string('equipment_name');
            $table->string('equipment_identifier')->nullable();
            $table->string('status')->default('working');
            $table->decimal('working_hours', 10, 4)->nullable();
            $table->decimal('idle_hours', 10, 4)->nullable();
            $table->string('fuel_type')->nullable();
            $table->decimal('fuel_quantity', 12, 4)->nullable();
            $table->decimal('rate_amount', 20, 4)->nullable();
            $table->decimal('amount', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'daily_site_report_id'], 'dsr_equipment_tenant_report_index');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_equipment_lines');
    }
};
