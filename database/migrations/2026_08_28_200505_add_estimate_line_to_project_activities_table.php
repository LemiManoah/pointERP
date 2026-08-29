<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_activities', function (Blueprint $table): void {
            $table->uuid('estimate_line_id')->nullable()->after('site_id');
            $table->uuid('estimate_work_item_key')->nullable()->after('estimate_line_id');
            $table->decimal('estimated_unit_cost', 20, 4)->nullable()->after('rate_amount');
            $table->foreign('estimate_line_id', 'proj_activity_est_line_fk')->references('id')->on('project_estimate_lines')->nullOnDelete();
            $table->unique(['project_id', 'estimate_work_item_key'], 'proj_activity_work_key_uq');
        });
    }

    public function down(): void
    {
        Schema::table('project_activities', function (Blueprint $table): void {
            $table->dropUnique('proj_activity_work_key_uq');
            $table->dropForeign('proj_activity_est_line_fk');
            $table->dropColumn(['estimate_line_id', 'estimate_work_item_key', 'estimated_unit_cost']);
        });
    }
};
