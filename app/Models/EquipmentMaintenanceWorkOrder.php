<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property-read string $equipment_id
 * @property-read string|null $equipment_maintenance_schedule_id
 * @property-read string $branch_id
 * @property-read string|null $project_id
 * @property-read string|null $site_id
 * @property-read string|null $equipment_location_id
 * @property-read string $reference
 * @property-read string $maintenance_type
 * @property-read string $priority
 * @property-read string $description
 * @property-read string $status
 * @property-read string|null $prior_equipment_status
 * @property-read CarbonInterface $reported_at
 * @property-read CarbonInterface|null $planned_start_at
 * @property-read CarbonInterface|null $actual_start_at
 * @property-read CarbonInterface|null $completed_at
 * @property-read CarbonInterface|null $cancelled_at
 * @property-read string|null $opening_meter_reading
 * @property-read string|null $closing_meter_reading
 * @property-read string|null $provider_customer_id
 * @property-read string|null $provider_name
 * @property-read string|null $downtime_hours
 * @property-read string|null $labour_cost
 * @property-read string|null $parts_cost
 * @property-read string|null $other_cost
 * @property-read string|null $total_cost
 * @property-read string|null $currency_code
 * @property-read string|null $findings
 * @property-read string|null $work_performed
 * @property-read string|null $completion_notes
 * @property-read string|null $cancellation_reason
 * @property-read CarbonInterface|null $next_service_date
 * @property-read string|null $next_service_reading
 * @property-read string $requested_by
 * @property-read string|null $approved_by
 * @property-read Equipment $equipment
 * @property-read EquipmentMaintenanceSchedule|null $schedule
 * @property-read Customer|null $provider
 * @property-read User $requestedBy
 * @property-read User|null $approvedBy
 * @property-read Collection<int, EquipmentMaintenancePartLine> $parts
 */
#[Fillable([
    'tenant_id', 'equipment_id', 'equipment_maintenance_schedule_id', 'branch_id',
    'project_id', 'site_id', 'equipment_location_id', 'reference', 'maintenance_type',
    'priority', 'description', 'status', 'prior_equipment_status', 'reported_at',
    'planned_start_at', 'actual_start_at', 'completed_at', 'cancelled_at',
    'opening_meter_reading', 'closing_meter_reading', 'provider_customer_id',
    'provider_name', 'downtime_hours', 'labour_cost', 'parts_cost', 'other_cost',
    'total_cost', 'currency_code', 'findings', 'work_performed', 'completion_notes',
    'cancellation_reason', 'next_service_date', 'next_service_reading', 'requested_by',
    'approved_by', 'supervised_by', 'completed_by', 'cancelled_by', 'created_by', 'updated_by',
])]
final class EquipmentMaintenanceWorkOrder extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<EquipmentMaintenanceWorkOrder>> */
    use HasFactory;

    use HasUuids;

    public const string STATUS_PLANNED = 'planned';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_CANCELLED = 'cancelled';

    public const array PRIORITIES = ['low', 'normal', 'high', 'critical'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'reported_at' => 'datetime', 'planned_start_at' => 'datetime',
            'actual_start_at' => 'datetime', 'completed_at' => 'datetime',
            'cancelled_at' => 'datetime', 'opening_meter_reading' => 'decimal:4',
            'closing_meter_reading' => 'decimal:4', 'downtime_hours' => 'decimal:4',
            'labour_cost' => 'decimal:4', 'parts_cost' => 'decimal:4',
            'other_cost' => 'decimal:4', 'total_cost' => 'decimal:4',
            'next_service_date' => 'date', 'next_service_reading' => 'decimal:4',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return BelongsTo<EquipmentMaintenanceSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EquipmentMaintenanceSchedule::class, 'equipment_maintenance_schedule_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'provider_customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<EquipmentMaintenancePartLine, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(EquipmentMaintenancePartLine::class);
    }
}
