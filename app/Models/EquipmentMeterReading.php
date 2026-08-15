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
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $equipment_id
 * @property-read string|null $project_id
 * @property-read string|null $site_id
 * @property-read string|null $equipment_location_id
 * @property-read string $event_type
 * @property-read string $reading_value
 * @property-read CarbonInterface $read_at
 * @property-read string|null $previous_reading
 * @property-read string|null $usage
 * @property-read string $status
 * @property-read string|null $corrects_reading_id
 * @property-read string|null $reason
 * @property-read string|null $evidence_note
 * @property-read string|null $decision_note
 * @property-read string|null $recorded_by
 * @property-read string|null $approved_by
 * @property-read CarbonInterface|null $approved_at
 * @property-read string|null $rejected_by
 * @property-read CarbonInterface|null $rejected_at
 * @property-read Equipment $equipment
 * @property-read User|null $recordedBy
 * @property-read User|null $approvedBy
 * @property-read User|null $rejectedBy
 * @property-read EquipmentMeterReading|null $correctedReading
 */
#[Fillable([
    'tenant_id', 'branch_id', 'equipment_id', 'project_id', 'site_id',
    'equipment_location_id', 'event_type', 'reading_value', 'read_at',
    'previous_reading', 'usage', 'status', 'source_type', 'source_id',
    'corrects_reading_id', 'reason', 'evidence_note', 'decision_note',
    'recorded_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at',
    'created_by', 'updated_by',
])]
final class EquipmentMeterReading extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentMeterReading>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_ACCEPTED = 'accepted';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_SUPERSEDED = 'superseded';

    public const array EVENT_TYPES = ['opening', 'assignment', 'daily_log', 'fuel', 'maintenance', 'return', 'transfer', 'manual', 'correction'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'reading_value' => 'decimal:4',
            'read_at' => 'datetime',
            'previous_reading' => 'decimal:4',
            'usage' => 'decimal:4',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
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

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'equipment_location_id');
    }

    /** @return BelongsTo<EquipmentMeterReading, $this> */
    public function correctedReading(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_reading_id');
    }

    /** @return HasMany<EquipmentMeterReading, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_reading_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<EquipmentMeterReading>  $query
     * @return Builder<EquipmentMeterReading>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('equipment.view-all') || $user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
