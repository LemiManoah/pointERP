<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_attendance_registers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('shift', 20);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUuid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'site_id', 'attendance_date', 'shift'], 'attendance_register_scope_unique');
            $table->index(['tenant_id', 'branch_id', 'status'], 'attendance_register_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_attendance_registers');
    }
};
