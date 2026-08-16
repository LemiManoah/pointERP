<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_site_report_equipment_lines', function (Blueprint $table): void {
            $table->foreignUuid('equipment_id')->nullable()->after('daily_site_report_id');
            $table->decimal('opening_meter_reading', 18, 4)->nullable()->after('idle_hours');
            $table->decimal('closing_meter_reading', 18, 4)->nullable()->after('opening_meter_reading');
            $table->string('fuel_transaction_type')->nullable()->after('fuel_quantity');
            $table->text('evidence_note')->nullable()->after('notes');
            $table->string('fleet_posting_status')->default('unposted')->after('evidence_note');
            $table->dateTime('fleet_posted_at')->nullable()->after('fleet_posting_status');

            $table->foreign('equipment_id', 'dsr_eq_line_equipment_fk')
                ->references('id')
                ->on('equipment')
                ->restrictOnDelete();
            $table->unique(['daily_site_report_id', 'equipment_id'], 'dsr_eq_line_report_asset_uq');
        });
    }

    public function down(): void
    {
        Schema::table('daily_site_report_equipment_lines', function (Blueprint $table): void {
            $table->dropUnique('dsr_eq_line_report_asset_uq');
            $table->dropForeign('dsr_eq_line_equipment_fk');
            $table->dropColumn([
                'equipment_id', 'opening_meter_reading', 'closing_meter_reading',
                'fuel_transaction_type', 'evidence_note', 'fleet_posting_status',
                'fleet_posted_at',
            ]);
        });
    }
};
