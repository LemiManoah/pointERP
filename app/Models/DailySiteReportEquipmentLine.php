<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'equipment_name', 'equipment_identifier', 'status', 'working_hours', 'idle_hours', 'fuel_type', 'fuel_quantity', 'rate_amount', 'amount', 'currency_code', 'notes', 'sort_order'])]
final class DailySiteReportEquipmentLine extends Model
{
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
            'fuel_quantity' => 'decimal:4',
            'rate_amount' => 'decimal:4',
            'amount' => 'decimal:4',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }
}
