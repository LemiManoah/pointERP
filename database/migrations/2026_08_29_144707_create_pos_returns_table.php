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
        Schema::create('pos_returns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('pos_sale_id');
            $table->string('return_number', 60);
            $table->string('status', 30);
            $table->text('reason');
            $table->decimal('refund_amount', 20, 4)->default(0);
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'pos_return_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('branch_id', 'pos_return_branch_fk')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('pos_sale_id', 'pos_return_sale_fk')->references('id')->on('pos_sales')->restrictOnDelete();
            $table->foreign('created_by', 'pos_return_created_by_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by', 'pos_return_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'return_number'], 'pos_return_tenant_number_uq');
            $table->index(['tenant_id', 'branch_id', 'status'], 'pos_return_scope_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_returns');
    }
};
