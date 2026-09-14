<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_resource_lines', function (Blueprint $table): void {
            $table->foreign('workforce_trade_id', 'est_res_workforce_trade_fk')
                ->references('id')
                ->on('workforce_trades')
                ->nullOnDelete();
        });

        Schema::table('work_item_resource_templates', function (Blueprint $table): void {
            $table->foreign('workforce_trade_id', 'wirt_workforce_trade_fk')
                ->references('id')
                ->on('workforce_trades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_item_resource_templates', function (Blueprint $table): void {
            $table->dropForeign('wirt_workforce_trade_fk');
        });

        Schema::table('estimate_resource_lines', function (Blueprint $table): void {
            $table->dropForeign('est_res_workforce_trade_fk');
        });
    }
};
