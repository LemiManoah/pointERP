<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('creates workforce trade foreign keys only after workforce trades exists', function (): void {
    expect(Schema::hasTable('workforce_trades'))->toBeTrue()
        ->and(Schema::hasTable('estimate_resource_lines'))->toBeTrue()
        ->and(Schema::hasTable('work_item_resource_templates'))->toBeTrue()
        ->and(Schema::hasColumn('estimate_resource_lines', 'workforce_trade_id'))->toBeTrue()
        ->and(Schema::hasColumn('work_item_resource_templates', 'workforce_trade_id'))->toBeTrue();
});

it('repairs missing legacy resource columns and tolerates a partially applied foreign key migration', function (): void {
    $foreignKeys = require database_path('migrations/2026_09_13_103630_add_workforce_trade_fk_to_resource_tables.php');
    $repair = require database_path('migrations/2026_09_13_103625_repair_legacy_estimate_resource_columns.php');
    $foreignKeyRepair = require database_path('migrations/2026_10_05_040924_ensure_workforce_trade_foreign_keys_on_resource_tables.php');
    $foreignKeys->down();
    foreach (['estimate_resource_lines', 'work_item_resource_templates'] as $tableName) {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('workforce_trade_id');
        });
    }

    $repair->up();
    $repair->up();
    Schema::table('estimate_resource_lines', function (Blueprint $table): void {
        $table->foreign('workforce_trade_id', 'est_res_workforce_trade_fk')->references('id')->on('workforce_trades')->nullOnDelete();
    });
    $foreignKeyRepair->up();
    $foreignKeyRepair->up();
    $foreignKeyRepair->down();
    expect(Schema::hasForeignKey('estimate_resource_lines', ['workforce_trade_id']))->toBeTrue()
        ->and(Schema::hasForeignKey('work_item_resource_templates', ['workforce_trade_id']))->toBeTrue();
    $repair->down();
    expect(Schema::hasColumn('estimate_resource_lines', 'workforce_trade_id'))->toBeTrue();
});
