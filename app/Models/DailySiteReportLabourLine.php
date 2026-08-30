<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DsrLabourSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'branch_id', 'daily_site_report_id', 'labour_source', 'subcontractor_id', 'trade_or_role', 'subcontractor_name', 'headcount', 'hours', 'rate_amount', 'amount', 'currency_code', 'notes', 'sort_order'])]
final class DailySiteReportLabourLine extends Model
{
    /** @use HasFactory<Factory<DailySiteReportLabourLine>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'labour_source' => DsrLabourSource::class,
            'headcount' => 'integer',
            'hours' => 'decimal:4',
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

    /** @return BelongsTo<Customer, $this> */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'subcontractor_id');
    }
}
