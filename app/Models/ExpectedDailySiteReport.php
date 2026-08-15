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
 * @property-read string $project_id
 * @property-read string $site_id
 * @property-read CarbonInterface $report_date
 * @property-read CarbonInterface|null $deadline_at
 * @property-read string $status
 * @property-read string|null $daily_site_report_id
 * @property-read CarbonInterface|null $submitted_at
 * @property-read CarbonInterface|null $notified_at
 * @property-read CarbonInterface|null $escalated_at
 * @property-read string|null $excuse_reason
 * @property-read string|null $marked_by
 * @property-read CarbonInterface|null $marked_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read DailySiteReport|null $report
 */
#[Fillable(['tenant_id', 'branch_id', 'project_id', 'site_id', 'report_date', 'deadline_at', 'status', 'daily_site_report_id', 'submitted_at', 'notified_at', 'escalated_at', 'excuse_reason', 'marked_by', 'marked_at'])]
final class ExpectedDailySiteReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpectedDailySiteReport>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_EXPECTED = 'expected';

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_LATE = 'late';

    public const string STATUS_MISSING = 'missing';

    public const string STATUS_EXCUSED = 'excused';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'report_date' => 'date',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'notified_at' => 'datetime',
            'escalated_at' => 'datetime',
            'marked_at' => 'datetime',
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

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
