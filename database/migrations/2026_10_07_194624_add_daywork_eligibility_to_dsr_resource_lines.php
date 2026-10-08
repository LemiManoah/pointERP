<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['daily_site_report_labour_lines', 'daily_site_report_equipment_lines', 'daily_site_report_material_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('work_type', 20)->default('ordinary');
            });
        }
    }

    public function down(): void
    {
        foreach (['daily_site_report_labour_lines', 'daily_site_report_equipment_lines', 'daily_site_report_material_lines'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('work_type');
            });
        }
    }
};
