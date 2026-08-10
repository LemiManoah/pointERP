<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_delay_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->string('delay_type')->nullable();
            $table->text('description');
            $table->decimal('hours_lost', 10, 4)->nullable();
            $table->text('action_taken')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'daily_site_report_id'], 'dsr_delay_tenant_report_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_delay_lines');
    }
};
