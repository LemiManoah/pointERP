<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $project_id
 * @property-read string $site_id
 * @property-read CarbonInterface $report_date
 * @property-read string $reference
 * @property-read string|null $weather
 * @property-read string|null $site_conditions
 * @property-read string|null $work_summary
 * @property-read string|null $delay_summary
 * @property-read string|null $visitor_summary
 * @property-read string|null $hse_notes
 * @property-read string|null $environment_notes
 * @property-read string|null $social_notes
 * @property-read string|null $completion_percent
 * @property-read string|null $output_value
 * @property-read string|null $input_cost
 * @property-read string|null $profit_loss
 * @property-read string $status
 * @property-read CarbonInterface|null $expected_at
 * @property-read string|null $submitted_by
 * @property-read CarbonInterface|null $submitted_at
 * @property-read string|null $reviewed_by
 * @property-read CarbonInterface|null $reviewed_at
 * @property-read string|null $approved_by
 * @property-read CarbonInterface|null $approved_at
 * @property-read string|null $returned_by
 * @property-read CarbonInterface|null $returned_at
 * @property-read string|null $return_reason
 * @property-read string $created_by
 * @property-read string|null $updated_by
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read CarbonInterface|null $deleted_at
 * @property-read Branch|null $branch
 * @property-read Project|null $project
 * @property-read Site|null $site
 * @property-read User|null $submittedBy
 * @property-read User|null $approvedBy
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'project_id',
    'site_id',
    'report_date',
    'reference',
    'weather',
    'site_conditions',
    'work_summary',
    'delay_summary',
    'visitor_summary',
    'hse_notes',
    'environment_notes',
    'social_notes',
    'completion_percent',
    'output_value',
    'input_cost',
    'profit_loss',
    'status',
    'expected_at',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'approved_by',
    'approved_at',
    'returned_by',
    'returned_at',
    'return_reason',
    'created_by',
    'updated_by',
])]
final class DailySiteReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<DailySiteReport>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SUBMITTED = 'submitted';

    public const string STATUS_REVIEWED = 'reviewed';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_RETURNED = 'returned';

    public const string STATUS_MISSING = 'missing';

    public const string STATUS_ARCHIVED = 'archived';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'tenant_id' => 'string',
            'branch_id' => 'string',
            'project_id' => 'string',
            'site_id' => 'string',
            'report_date' => 'date',
            'completion_percent' => 'decimal:4',
            'output_value' => 'decimal:4',
            'input_cost' => 'decimal:4',
            'profit_loss' => 'decimal:4',
            'expected_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<DailySiteReportWorkLine, $this>
     */
    public function workLines(): HasMany
    {
        return $this->hasMany(DailySiteReportWorkLine::class);
    }

    /**
     * @return HasMany<DailySiteReportLabourLine, $this>
     */
    public function labourLines(): HasMany
    {
        return $this->hasMany(DailySiteReportLabourLine::class);
    }

    /**
     * @return HasMany<DailySiteReportEquipmentLine, $this>
     */
    public function equipmentLines(): HasMany
    {
        return $this->hasMany(DailySiteReportEquipmentLine::class);
    }

    /**
     * @return HasMany<DailySiteReportMaterialLine, $this>
     */
    public function materialLines(): HasMany
    {
        return $this->hasMany(DailySiteReportMaterialLine::class);
    }

    /**
     * @return HasMany<DailySiteReportCostLine, $this>
     */
    public function costLines(): HasMany
    {
        return $this->hasMany(DailySiteReportCostLine::class);
    }

    /**
     * @return HasMany<DailySiteReportDelayLine, $this>
     */
    public function delayLines(): HasMany
    {
        return $this->hasMany(DailySiteReportDelayLine::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_REVIEWED], true);
    }

    /**
     * @param  Builder<DailySiteReport>  $query
     * @return Builder<DailySiteReport>
     */
    protected function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_REVIEWED,
            self::STATUS_RETURNED,
            self::STATUS_MISSING,
        ]);
    }
}
