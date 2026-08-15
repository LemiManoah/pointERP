<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('equipment')
            ->where('meter_type', '!=', 'none')
            ->whereNotNull('starting_meter_reading')
            ->whereNotNull('starting_meter_date')
            ->orderBy('id')
            ->get()
            ->each(function (object $equipment): void {
                $values = get_object_vars($equipment);
                DB::table('equipment_meter_readings')->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $values['tenant_id'],
                    'branch_id' => $values['branch_id'],
                    'equipment_id' => $values['id'],
                    'project_id' => null,
                    'site_id' => null,
                    'equipment_location_id' => $values['default_location_id'],
                    'event_type' => 'opening',
                    'reading_value' => $values['starting_meter_reading'],
                    'read_at' => $values['starting_meter_date'],
                    'previous_reading' => null,
                    'usage' => null,
                    'status' => 'accepted',
                    'recorded_by' => $values['created_by'],
                    'approved_by' => $values['created_by'],
                    'approved_at' => $values['starting_meter_date'],
                    'created_by' => $values['created_by'],
                    'updated_by' => $values['updated_by'],
                    'created_at' => $values['created_at'],
                    'updated_at' => $values['updated_at'],
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('equipment_meter_readings')
            ->where('event_type', 'opening')
            ->whereNull('source_type')
            ->delete();
    }
};
