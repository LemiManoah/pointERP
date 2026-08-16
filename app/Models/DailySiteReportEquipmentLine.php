<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property-read string $daily_site_report_id
 * @property-read string|null $equipment_id
 * @property-read string $equipment_name
 * @property-read string|null $equipment_identifier
 * @property-read string $status
 * @property-read string|null $working_hours
 * @property-read string|null $idle_hours
 * @property-read string|null $opening_meter_reading
 * @property-read string|null $closing_meter_reading
 * @property-read string|null $fuel_type
 * @property-read string|null $fuel_quantity
 * @property-read string|null $fuel_transaction_type
 * @property-read string|null $notes
 * @property-read string|null $evidence_note
 * @property-read string $fleet_posting_status
 * @property-read DailySiteReport $report
 * @property-read Equipment|null $equipment
 */
#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'equipment_id', 'equipment_name', 'equipment_identifier', 'status', 'working_hours', 'idle_hours', 'opening_meter_reading', 'closing_meter_reading', 'fuel_type', 'fuel_quantity', 'fuel_transaction_type', 'rate_amount', 'amount', 'currency_code', 'notes', 'evidence_note', 'fleet_posting_status', 'fleet_posted_at', 'sort_order'])]
final class DailySiteReportEquipmentLine extends Model
{
    /** @use HasFactory<Factory<DailySiteReportEquipmentLine>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'working_hours' => 'decimal:4',
            'idle_hours' => 'decimal:4',
            'opening_meter_reading' => 'decimal:4',
            'closing_meter_reading' => 'decimal:4',
            'fuel_quantity' => 'decimal:4',
            'rate_amount' => 'decimal:4',
            'amount' => 'decimal:4',
            'fleet_posted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DailySiteReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
