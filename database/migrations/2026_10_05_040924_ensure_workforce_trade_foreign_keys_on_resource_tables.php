<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['estimate_resource_lines' => 'est_res_workforce_trade_fk', 'work_item_resource_templates' => 'wirt_workforce_trade_fk'] as $tableName => $constraint) {
            if (! Schema::hasForeignKey($tableName, ['workforce_trade_id'])) {
                Schema::table($tableName, function (Blueprint $table) use ($constraint): void {
                    $table->foreign('workforce_trade_id', $constraint)
                        ->references('id')->on('workforce_trades')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Keep repaired constraints: they may predate this migration and belong to the original schema.
    }
};
