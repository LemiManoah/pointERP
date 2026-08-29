<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('expense_id');
            $table->uuid('expense_item_id');
            $table->uuid('project_id')->nullable();
            $table->uuid('site_id')->nullable();
            $table->uuid('project_activity_id')->nullable();
            $table->string('expense_category_name_snapshot', 120);
            $table->string('expense_item_name_snapshot', 160);
            $table->text('description')->nullable();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_amount', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->decimal('base_currency_amount', 20, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'expense_line_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('expense_id', 'expense_line_expense_fk')->references('id')->on('expenses')->cascadeOnDelete();
            $table->foreign('expense_item_id', 'expense_line_item_fk')->references('id')->on('expense_items')->restrictOnDelete();
            $table->foreign('project_id', 'expense_line_project_fk')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('site_id', 'expense_line_site_fk')->references('id')->on('sites')->restrictOnDelete();
            $table->foreign('project_activity_id', 'expense_line_activity_fk')->references('id')->on('project_activities')->restrictOnDelete();
            $table->index(['tenant_id', 'expense_id'], 'expense_line_scope_idx');
            $table->index(['tenant_id', 'project_id', 'site_id'], 'expense_line_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
    }
};
