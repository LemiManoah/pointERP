<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string|null $branch_id
 * @property-read string|null $project_id
 * @property-read string|null $site_id
 * @property-read string $name
 * @property-read string $timezone
 * @property-read string $reporting_deadline
 * @property-read list<int> $working_days
 * @property-read int $missing_escalation_days
 * @property-read bool $is_active
 * @property-read string|null $created_by
 * @property-read Project|null $project
 * @property-read Site|null $site
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReportingCalendarException> $exceptions
 */
#[Fillable(['tenant_id', 'branch_id', 'project_id', 'site_id', 'name', 'timezone', 'reporting_deadline', 'working_days', 'missing_escalation_days', 'is_active', 'created_by', 'updated_by'])]
final class ReportingCalendar extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'working_days' => 'array',
            'missing_escalation_days' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<ReportingCalendarException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ReportingCalendarException::class);
    }

    public function isReportingDay(CarbonInterface $date): bool
    {
        $exception = $this->exceptions->first(
            fn (ReportingCalendarException $exception): bool => $exception->exception_date->isSameDay($date),
        );

        if ($exception instanceof ReportingCalendarException) {
            return $exception->type === ReportingCalendarException::TYPE_WORKING_OVERRIDE;
        }

        return in_array($date->dayOfWeekIso, $this->working_days ?? [], true);
    }
}
