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
 * @property-read string $equipment_id
 * @property-read string $source_branch_id
 * @property-read string $destination_branch_id
 * @property-read string $destination_location_id
 * @property-read string|null $destination_project_id
 * @property-read string|null $destination_site_id
 * @property-read string $reason
 * @property-read string|null $transport_reference
 * @property-read string $status
 * @property-read string $requested_by
 * @property-read string|null $approved_by
 * @property-read string|null $dispatched_by
 * @property-read string|null $dispatch_condition
 * @property-read string|null $receipt_condition
 * @property-read CarbonInterface $requested_at
 * @property-read CarbonInterface|null $approved_at
 * @property-read CarbonInterface|null $dispatched_at
 * @property-read CarbonInterface|null $received_at
 * @property-read string|null $dispatch_meter_reading
 * @property-read string|null $receipt_meter_reading
 * @property-read Equipment $equipment
 * @property-read Branch $sourceBranch
 * @property-read Branch $destinationBranch
 * @property-read EquipmentLocation|null $sourceLocation
 * @property-read EquipmentLocation $destinationLocation
 * @property-read Project|null $destinationProject
 * @property-read Site|null $destinationSite
 * @property-read User $requestedBy
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'source_branch_id', 'source_location_id',
    'source_project_id', 'source_site_id', 'destination_branch_id',
    'destination_location_id', 'destination_project_id', 'destination_site_id',
    'reason', 'transport_reference', 'status', 'requested_at', 'approved_at',
    'dispatched_at', 'received_at', 'dispatch_meter_reading', 'receipt_meter_reading',
    'dispatch_condition', 'receipt_condition', 'requested_by', 'approved_by',
    'dispatched_by', 'received_by', 'created_by', 'updated_by',
])]
final class EquipmentTransfer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentTransfer>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_REQUESTED = 'requested';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_DISPATCHED = 'dispatched';

    public const string STATUS_RECEIVED = 'received';

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'requested_at' => 'datetime', 'approved_at' => 'datetime',
            'dispatched_at' => 'datetime', 'received_at' => 'datetime',
            'dispatch_meter_reading' => 'decimal:4', 'receipt_meter_reading' => 'decimal:4',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'source_location_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'destination_location_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function destinationProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'destination_project_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function destinationSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'destination_site_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
