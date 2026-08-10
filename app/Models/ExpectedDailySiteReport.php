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

#[Fillable(['tenant_id', 'branch_id', 'project_id', 'site_id', 'report_date', 'deadline_at', 'status', 'daily_site_report_id', 'notified_at', 'escalated_at'])]
final class ExpectedDailySiteReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpectedDailySiteReport>> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'report_date' => 'date',
            'deadline_at' => 'datetime',
            'notified_at' => 'datetime',
            'escalated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }
}
