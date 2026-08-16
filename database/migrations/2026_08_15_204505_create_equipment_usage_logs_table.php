<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_equipment_line_id')->nullable();
            $table->date('usage_date');
            $table->string('operating_status');
            $table->decimal('opening_meter_reading', 18, 4)->nullable();
            $table->decimal('closing_meter_reading', 18, 4)->nullable();
            $table->decimal('meter_usage', 18, 4)->nullable();
            $table->decimal('working_hours', 12, 4)->nullable();
            $table->decimal('idle_hours', 12, 4)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('posted');
            $table->foreignUuid('posted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('posted_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('daily_site_report_equipment_line_id', 'eq_usage_dsr_line_fk')
                ->references('id')
                ->on('daily_site_report_equipment_lines')
                ->restrictOnDelete();
            $table->unique('daily_site_report_equipment_line_id', 'eq_usage_dsr_line_uq');
            $table->index(['tenant_id', 'branch_id', 'usage_date'], 'eq_usage_scope_date_idx');
            $table->index(['equipment_id', 'usage_date'], 'eq_usage_asset_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_usage_logs');
    }
};
