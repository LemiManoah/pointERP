<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates workforce trade foreign keys only after workforce trades exists', function (): void {
    expect(Schema::hasTable('workforce_trades'))->toBeTrue()
        ->and(Schema::hasTable('estimate_resource_lines'))->toBeTrue()
        ->and(Schema::hasTable('work_item_resource_templates'))->toBeTrue()
        ->and(Schema::hasColumn('estimate_resource_lines', 'workforce_trade_id'))->toBeTrue()
        ->and(Schema::hasColumn('work_item_resource_templates', 'workforce_trade_id'))->toBeTrue();
});
