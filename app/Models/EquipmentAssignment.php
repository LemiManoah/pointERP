<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $equipment_id
 * @property-read string $branch_id
 * @property-read string $status
 * @property-read CarbonInterface $assigned_at
 * @property-read CarbonInterface|null $returned_at
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'branch_id', 'project_id', 'site_id',
    'equipment_location_id', 'custodian_staff_id', 'external_custodian_name',
    'external_custodian_employer', 'assigned_at', 'expected_return_at', 'returned_at',
    'handover_meter_reading', 'return_meter_reading', 'handover_condition',
    'return_condition', 'assignment_notes', 'return_notes', 'status', 'handed_over_by',
    'received_by', 'returned_by', 'accepted_return_by', 'return_location_id',
    'created_by', 'updated_by',
])]
final class EquipmentAssignment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_RETURNED = 'returned';

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'assigned_at' => 'datetime', 'expected_return_at' => 'datetime',
            'returned_at' => 'datetime', 'handover_meter_reading' => 'decimal:4',
            'return_meter_reading' => 'decimal:4', 'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
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

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'equipment_location_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function returnLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'return_location_id');
    }

    /** @return BelongsTo<Staff, $this> */
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'custodian_staff_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedReturnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_return_by');
    }
}
