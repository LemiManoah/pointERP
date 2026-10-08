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
        foreach (['project_estimate_lines', 'project_activities', 'daily_site_report_work_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignUuid('boq_item_id')->nullable()->constrained('project_boq_items');
            });
        }

        Schema::table('project_activities', function (Blueprint $table): void {
            $table->string('progress_method', 20)->default('measured');
        });
        Schema::table('daily_site_report_work_lines', function (Blueprint $table): void {
            $table->boolean('counts_towards_boq')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['daily_site_report_work_lines', 'project_activities', 'project_estimate_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('boq_item_id');
            });
        }

        Schema::table('project_activities', fn (Blueprint $table) => $table->dropColumn('progress_method'));
        Schema::table('daily_site_report_work_lines', fn (Blueprint $table) => $table->dropColumn('counts_towards_boq'));
    }
};
