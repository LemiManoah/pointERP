<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $daily_site_report_correction_id
 * @property-read string $daily_site_report_equipment_line_id
 * @property-read string $equipment_id
 * @property-read string $working_hours_delta
 * @property-read string $idle_hours_delta
 * @property-read string $fuel_quantity_delta
 * @property-read string $reason
 * @property-read string|null $equipment_usage_log_id
 * @property-read string|null $equipment_fuel_transaction_id
 * @property-read string $approved_by
 * @property-read CarbonInterface $approved_at
 */
#[Fillable([
    'tenant_id', 'branch_id', 'daily_site_report_correction_id',
    'daily_site_report_equipment_line_id', 'equipment_id', 'working_hours_delta',
    'idle_hours_delta', 'fuel_quantity_delta', 'reason', 'equipment_usage_log_id',
    'equipment_fuel_transaction_id', 'approved_by', 'approved_at',
])]
final class DsrEquipmentLineAdjustment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DsrEquipmentLineAdjustment>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'working_hours_delta' => 'decimal:4',
            'idle_hours_delta' => 'decimal:4',
            'fuel_quantity_delta' => 'decimal:4',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DailySiteReportCorrection, $this> */
    public function correction(): BelongsTo
    {
        return $this->belongsTo(DailySiteReportCorrection::class, 'daily_site_report_correction_id');
    }

    /** @return BelongsTo<DailySiteReportEquipmentLine, $this> */
    public function reportEquipmentLine(): BelongsTo
    {
        return $this->belongsTo(DailySiteReportEquipmentLine::class, 'daily_site_report_equipment_line_id');
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<EquipmentUsageLog, $this> */
    public function usageLog(): BelongsTo
    {
        return $this->belongsTo(EquipmentUsageLog::class, 'equipment_usage_log_id');
    }

    /** @return BelongsTo<EquipmentFuelTransaction, $this> */
    public function fuelTransaction(): BelongsTo
    {
        return $this->belongsTo(EquipmentFuelTransaction::class, 'equipment_fuel_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
