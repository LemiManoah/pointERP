<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type')->default('client');
            $table->string('name');
            $table->string('code');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('title');
            $table->text('scope_summary')->nullable();
            $table->decimal('contract_value', 20, 4)->nullable();
            $table->char('currency_code', 3);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('retention_percent', 8, 4)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('base_currency_code', 3);
            $table->decimal('budget_amount', 20, 4)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->time('reporting_deadline')->nullable();
            $table->string('status')->default('planned');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('sites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('name');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('reporting_deadline')->nullable();
            $table->string('status')->default('planned');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'reference']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'project_id']);
        });

        Schema::create('project_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('boq_item_number')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('planned_quantity', 20, 4)->nullable();
            $table->decimal('approved_quantity', 20, 4)->default(0);
            $table->decimal('rate_amount', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'project_id', 'status']);
            $table->index(['tenant_id', 'boq_item_number']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('can_manage')->default(false);
            $table->timestamps();

            $table->primary(['project_id', 'user_id']);
        });

        Schema::create('site_user', function (Blueprint $table): void {
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('can_submit_dsr')->default(false);
            $table->boolean('can_review_dsr')->default(false);
            $table->timestamps();

            $table->primary(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('project_activities');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('customers');
    }
};
