<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['estimate_resource_lines', 'work_item_resource_templates'] as $tableName) {
            foreach (['equipment_category_id', 'workforce_trade_id', 'subcontractor_id'] as $column) {
                if (! Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column): void {
                        $table->uuid($column)->nullable();
                    });
                }
            }

            $prefix = $tableName === 'estimate_resource_lines' ? 'est_res' : 'wirt';
            foreach (['equipment_category_id' => ['equipment_categories', 'equipment_category'], 'subcontractor_id' => ['customers', 'subcontractor']] as $column => [$referencedTable, $suffix]) {
                if (! Schema::hasForeignKey($tableName, [$column])) {
                    Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $prefix, $suffix): void {
                        $table->foreign($column, $prefix.'_'.$suffix.'_fk')->references('id')->on($referencedTable)->nullOnDelete();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Compatibility repair: these columns may predate this migration and contain live data.
    }
};
