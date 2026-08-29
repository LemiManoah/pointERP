<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectEstimateStatus;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $project_id
 * @property-read int $version_number
 * @property-read string $title
 * @property-read string $currency_code
 * @property-read ProjectEstimateStatus $status
 * @property-read bool $is_baseline
 * @property-read string|null $notes
 * @property-read string|null $approved_by
 * @property-read CarbonInterface|null $approved_at
 * @property-read Project $project
 * @property-read Collection<int, ProjectEstimateLine> $lines
 * @property-read int|null $lines_count
 */
#[Fillable(['tenant_id', 'branch_id', 'project_id', 'version_number', 'title', 'currency_code', 'status', 'is_baseline', 'notes', 'approved_by', 'approved_at', 'created_by', 'updated_by'])]
final class ProjectEstimate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ProjectEstimate>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'version_number' => 'integer',
            'status' => ProjectEstimateStatus::class,
            'is_baseline' => 'boolean',
            'approved_at' => 'datetime',
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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<ProjectEstimateLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ProjectEstimateLine::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->status === ProjectEstimateStatus::Draft;
    }
}
