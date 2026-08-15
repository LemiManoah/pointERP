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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $branch_id
 * @property-read string $equipment_category_id
 * @property-read string $asset_code
 * @property-read string $name
 * @property-read string|null $owner_customer_id
 * @property-read string|null $owner_name
 * @property-read CarbonInterface|null $acquired_on
 * @property-read CarbonInterface|null $starting_meter_date
 * @property-read CarbonInterface|null $current_meter_read_at
 * @property-read Customer|null $owner
 */
#[Fillable([
    'tenant_id', 'branch_id', 'equipment_category_id', 'asset_code', 'name', 'make',
    'model', 'model_year', 'serial_number', 'registration_number', 'chassis_number',
    'ownership_type', 'owner_customer_id', 'owner_name', 'capacity_value', 'capacity_unit',
    'acquired_on', 'acquisition_amount', 'acquisition_currency_code', 'hire_rate',
    'hire_rate_basis', 'default_location_id', 'meter_type', 'starting_meter_reading',
    'starting_meter_date', 'fuel_efficiency_basis', 'expected_fuel_efficiency',
    'fuel_tolerance_percent', 'tank_capacity', 'current_status', 'current_location_id',
    'current_project_id', 'current_site_id', 'current_custodian_id',
    'current_meter_reading', 'current_meter_read_at', 'condition_summary', 'is_active',
    'created_by', 'updated_by',
])]
final class Equipment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<Equipment>> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const array METER_TYPES = ['odometer_km', 'engine_hours', 'operating_hours', 'none'];

    public const array OWNERSHIP_TYPES = ['owned', 'leased', 'hired', 'subcontractor'];

    public const array STATUSES = ['available', 'assigned', 'idle', 'under_maintenance', 'out_of_service', 'transferred', 'retired'];

    /** @return array<string, string> */
    public function casts(): array
    {
        return [
            'model_year' => 'integer', 'capacity_value' => 'decimal:4',
            'acquired_on' => 'date', 'acquisition_amount' => 'decimal:4',
            'hire_rate' => 'decimal:4', 'starting_meter_reading' => 'decimal:4',
            'starting_meter_date' => 'date', 'expected_fuel_efficiency' => 'decimal:4',
            'fuel_tolerance_percent' => 'decimal:4', 'tank_capacity' => 'decimal:4',
            'current_meter_reading' => 'decimal:4', 'current_meter_read_at' => 'datetime',
            'is_active' => 'boolean', 'created_at' => 'datetime', 'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'default_location_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'current_location_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function currentProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'current_project_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    /** @return BelongsTo<Staff, $this> */
    public function currentCustodian(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'current_custodian_id');
    }

    /** @return BelongsTo<Currency, $this> */
    public function acquisitionCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'acquisition_currency_code', 'code');
    }

    /** @return MorphMany<DocumentLink, $this> */
    public function documentLinks(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    /**
     * @param  Builder<Equipment>  $query
     * @return Builder<Equipment>
     */
    protected function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('equipment.view-all') || $user->can('branches.view-all')) {
            return $query;
        }

        return $query->whereIn('branch_id', $user->branches()->pluck('branches.id'));
    }
}
