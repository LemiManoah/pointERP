<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_work_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('boq_item_number')->nullable();
            $table->string('description');
            $table->string('chainage_from')->nullable();
            $table->string('chainage_to')->nullable();
            $table->string('side')->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->string('unit')->nullable();
            $table->decimal('rate_amount', 20, 4)->nullable();
            $table->decimal('amount', 20, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'daily_site_report_id'], 'dsr_work_tenant_report_index');
            $table->index(['tenant_id', 'boq_item_number'], 'dsr_work_tenant_boq_index');
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_work_lines');
    }
};
