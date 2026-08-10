<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

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
