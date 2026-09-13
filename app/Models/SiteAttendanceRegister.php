<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceShift;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $project_id
 * @property-read string $site_id
 * @property-read CarbonInterface $attendance_date
 * @property-read AttendanceShift $shift
 * @property-read AttendanceRegisterStatus $status
 * @property-read string|null $notes
 * @property-read string $recorded_by
 * @property-read string|null $confirmed_by
 * @property-read CarbonInterface|null $confirmed_at
 * @property-read string|null $reopened_by
 * @property-read CarbonInterface|null $reopened_at
 * @property-read string|null $reopen_reason
 * @property-read Collection<int, SiteAttendanceRecord> $records
 */
#[Fillable([
    'tenant_id',
    'branch_id',
    'project_id',
    'site_id',
    'attendance_date',
    'shift',
    'status',
    'notes',
    'recorded_by',
    'confirmed_by',
    'confirmed_at',
    'reopened_by',
    'reopened_at',
    'reopen_reason',
])]
final class SiteAttendanceRegister extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<SiteAttendanceRegister>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'shift' => AttendanceShift::class,
            'status' => AttendanceRegisterStatus::class,
            'confirmed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /** @return HasMany<SiteAttendanceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(SiteAttendanceRecord::class);
    }

    public function isDraft(): bool
    {
        return $this->status === AttendanceRegisterStatus::Draft;
    }
}
