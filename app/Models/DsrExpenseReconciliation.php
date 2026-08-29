<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'daily_site_report_cost_line_id', 'expense_line_id', 'reconciled_by', 'reconciled_at', 'reason'])]
final class DsrExpenseReconciliation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DsrExpenseReconciliation>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['reconciled_at' => 'datetime'];
    }

    /** @return BelongsTo<DailySiteReportCostLine, $this> */
    public function dsrCostLine(): BelongsTo
    {
        return $this->belongsTo(DailySiteReportCostLine::class, 'daily_site_report_cost_line_id');
    }

    /** @return BelongsTo<ExpenseLine, $this> */
    public function expenseLine(): BelongsTo
    {
        return $this->belongsTo(ExpenseLine::class);
    }
}
