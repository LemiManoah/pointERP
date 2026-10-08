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
        Schema::create('boq_progress_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained();
            $table->foreignUuid('project_id')->constrained();
            $table->foreignUuid('boq_item_id')->constrained('project_boq_items');
            $table->foreignUuid('project_activity_id')->constrained();
            $table->foreignUuid('estimate_line_id')->nullable()->constrained('project_estimate_lines');
            $table->foreignUuid('daily_site_report_work_line_id')->nullable()->constrained();
            $table->string('source_key')->unique();
            $table->decimal('quantity', 20, 4);
            $table->string('unit', 40);
            $table->date('measurement_date');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_progress_entries');
    }
};
