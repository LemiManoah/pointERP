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
        Schema::create('equipment_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignUuid('source_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('source_location_id')->nullable()->constrained('equipment_locations')->restrictOnDelete();
            $table->foreignUuid('source_project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('source_site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('destination_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignUuid('destination_location_id')->constrained('equipment_locations')->restrictOnDelete();
            $table->foreignUuid('destination_project_id')->nullable()->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('destination_site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->text('reason');
            $table->string('transport_reference')->nullable();
            $table->string('status')->default('requested');
            $table->dateTime('requested_at');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->decimal('dispatch_meter_reading', 18, 4)->nullable();
            $table->decimal('receipt_meter_reading', 18, 4)->nullable();
            $table->text('dispatch_condition')->nullable();
            $table->text('receipt_condition')->nullable();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'eq_transfer_scope_status_idx');
            $table->index(['equipment_id', 'status'], 'eq_transfer_asset_status_idx');
            $table->index(['destination_branch_id', 'status'], 'eq_transfer_dest_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_transfers');
    }
};
