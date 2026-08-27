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
        Schema::create('material_requisitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('requesting_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 40);
            $table->string('department', 120)->nullable();
            $table->date('required_by_date');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('draft');
            $table->text('reason');
            $table->text('decision_reason')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference'], 'mat_req_tenant_ref_uq');
            $table->index(['tenant_id', 'branch_id', 'status'], 'mat_req_scope_status_idx');
            $table->index(['tenant_id', 'inventory_store_id', 'status'], 'mat_req_store_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_requisitions');
    }
};
