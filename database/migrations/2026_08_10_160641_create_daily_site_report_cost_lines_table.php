<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_cost_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('description');
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('rate_amount', 20, 4)->nullable();
            $table->decimal('amount', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'daily_site_report_id'], 'dsr_cost_tenant_report_index');
            $table->index(['tenant_id', 'category'], 'dsr_cost_tenant_category_index');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_cost_lines');
    }
};
