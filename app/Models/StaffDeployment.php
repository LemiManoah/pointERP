<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffDeploymentStatus;
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
 * @property-read string $staff_id
 * @property-read string $project_id
 * @property-read string|null $site_id
 * @property-read string|null $workforce_trade_id
 * @property-read CarbonInterface $starts_on
 * @property-read CarbonInterface|null $ends_on
 * @property-read StaffDeploymentStatus $status
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'staff_id',
    'project_id',
    'site_id',
    'workforce_trade_id',
    'starts_on',
    'ends_on',
    'status',
    'notes',
    'assigned_by',
    'ended_by',
])]
final class StaffDeployment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<StaffDeployment>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => StaffDeploymentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
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

    /** @return BelongsTo<WorkforceTrade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(WorkforceTrade::class, 'workforce_trade_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
