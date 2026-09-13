<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_deployments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('workforce_trade_id')->nullable()->constrained('workforce_trades')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->foreignUuid('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'staff_id', 'status'], 'staff_deployments_staff_status_index');
            $table->index(['tenant_id', 'project_id', 'site_id'], 'staff_deployments_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deployments');
    }
};
