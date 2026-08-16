<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $equipment_id
 * @property-read string $branch_id
 * @property-read string|null $daily_site_report_equipment_line_id
 * @property-read CarbonInterface $usage_date
 * @property-read string $operating_status
 * @property-read string|null $opening_meter_reading
 * @property-read string|null $closing_meter_reading
 * @property-read string|null $meter_usage
 * @property-read string|null $working_hours
 * @property-read string|null $idle_hours
 * @property-read string $status
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'branch_id', 'project_id', 'site_id',
    'equipment_location_id', 'daily_site_report_equipment_line_id', 'usage_date',
    'operating_status', 'opening_meter_reading', 'closing_meter_reading', 'meter_usage',
    'working_hours', 'idle_hours', 'notes', 'status', 'posted_by', 'posted_at',
    'created_by', 'updated_by',
])]
final class EquipmentUsageLog extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'usage_date' => 'date', 'opening_meter_reading' => 'decimal:4',
            'closing_meter_reading' => 'decimal:4', 'meter_usage' => 'decimal:4',
            'working_hours' => 'decimal:4', 'idle_hours' => 'decimal:4',
            'posted_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<DailySiteReportEquipmentLine, $this> */
    public function dailySiteReportEquipmentLine(): BelongsTo
    {
        return $this->belongsTo(DailySiteReportEquipmentLine::class);
    }
}
