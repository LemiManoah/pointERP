<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_attendance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_attendance_register_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->foreignUuid('subcontractor_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('workforce_trade_id')->constrained('workforce_trades')->restrictOnDelete();
            $table->string('labour_source', 30);
            $table->string('worker_name_snapshot')->nullable();
            $table->string('subcontractor_name_snapshot')->nullable();
            $table->unsignedInteger('headcount')->default(1);
            $table->string('attendance_status', 40)->default('present');
            $table->decimal('regular_hours_per_person', 6, 2)->default(8);
            $table->decimal('overtime_hours_per_person', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'staff_id'], 'attendance_records_staff_index');
            $table->index(['site_attendance_register_id', 'workforce_trade_id'], 'attendance_records_trade_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_attendance_records');
    }
};
