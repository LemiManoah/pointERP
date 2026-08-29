<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'expense_cat_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by', 'expense_cat_created_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'expense_cat_updated_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'expense_cat_tenant_code_uq');
            $table->index(['tenant_id', 'is_active'], 'expense_cat_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
