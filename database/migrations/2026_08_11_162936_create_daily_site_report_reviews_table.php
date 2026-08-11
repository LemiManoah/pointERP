<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'daily_site_report_id'], 'dsr_reviews_report_index');
            $table->index(['tenant_id', 'action', 'created_at'], 'dsr_reviews_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_reviews');
    }
};
