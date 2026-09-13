<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_site_report_labour_lines', function (Blueprint $table): void {
            $table->foreignUuid('workforce_trade_id')->nullable()->after('subcontractor_id');
            $table->foreign('workforce_trade_id', 'dsr_labour_trade_fk')
                ->references('id')
                ->on('workforce_trades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_site_report_labour_lines', function (Blueprint $table): void {
            $table->dropForeign('dsr_labour_trade_fk');
            $table->dropColumn('workforce_trade_id');
        });
    }
};
