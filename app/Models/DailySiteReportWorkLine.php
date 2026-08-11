<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'branch_id',
    'daily_site_report_id',
    'project_activity_id',
    'site_id',
    'boq_item_number',
    'description',
    'chainage_from',
    'chainage_to',
    'side',
    'quantity',
    'unit',
    'rate_amount',
    'amount',
    'currency_code',
    'notes',
    'sort_order',
])]
final class DailySiteReportWorkLine extends Model
{
    /** @use HasFactory<Factory<DailySiteReportWorkLine>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate_amount' => 'decimal:4',
            'amount' => 'decimal:4',
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
}
