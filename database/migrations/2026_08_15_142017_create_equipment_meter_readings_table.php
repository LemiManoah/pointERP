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
        Schema::create('equipment_meter_readings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('equipment_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->decimal('reading_value', 18, 4);
            $table->dateTime('read_at');
            $table->decimal('previous_reading', 18, 4)->nullable();
            $table->decimal('usage', 18, 4)->nullable();
            $table->string('status')->default('accepted');
            $table->nullableMorphs('source', 'eq_meter_source_idx');
            $table->uuid('corrects_reading_id')->nullable();
            $table->text('reason')->nullable();
            $table->text('evidence_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('corrects_reading_id', 'eq_meter_corrects_fk')->references('id')->on('equipment_meter_readings')->restrictOnDelete();
            $table->index(['tenant_id', 'equipment_id', 'status', 'read_at'], 'eq_meter_asset_status_time_idx');
            $table->index(['tenant_id', 'branch_id', 'read_at'], 'eq_meter_scope_time_idx');
            $table->index(['corrects_reading_id', 'status'], 'eq_meter_correction_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_meter_readings');
    }
};
