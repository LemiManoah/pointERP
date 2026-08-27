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
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('source_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->foreignUuid('destination_store_id')->constrained('inventory_stores')->restrictOnDelete();
            $table->string('reference', 40);
            $table->string('request_key', 120);
            $table->string('status', 24)->default('pending_approval');
            $table->text('reason');
            $table->text('decision_reason')->nullable();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference'], 'inv_transfer_ref_uq');
            $table->unique(['tenant_id', 'request_key'], 'inv_transfer_request_uq');
            $table->index(['tenant_id', 'branch_id', 'status'], 'inv_transfer_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
