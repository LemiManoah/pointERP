<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activities');
    }
};
