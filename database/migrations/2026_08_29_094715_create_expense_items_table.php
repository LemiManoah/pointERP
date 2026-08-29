<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('expense_category_id');
            $table->uuid('default_unit_of_measure_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'expense_item_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('expense_category_id', 'expense_item_category_fk')->references('id')->on('expense_categories')->restrictOnDelete();
            $table->foreign('default_unit_of_measure_id', 'expense_item_unit_fk')->references('id')->on('unit_of_measures')->nullOnDelete();
            $table->foreign('created_by', 'expense_item_created_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'expense_item_updated_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'expense_item_tenant_code_uq');
            $table->index(['tenant_id', 'expense_category_id', 'is_active'], 'expense_item_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
