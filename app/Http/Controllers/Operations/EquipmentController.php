<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\SaveEquipment;
use App\Actions\Operations\Equipment\SetEquipmentActiveStatus;
use App\Http\Controllers\Operations\Concerns\PresentsLinkedDocuments;
use App\Http\Requests\Operations\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Operations\Equipment\UpdateEquipmentRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EquipmentController
{
    use PresentsLinkedDocuments;

    public function index(): Response
    {
        Gate::authorize('viewAny', Equipment::class);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $canViewCosts = $user->can('equipment.costs.view');

        return Inertia::render('operations/equipment/index', [
            'activeTab' => request()->string('tab')->value() ?: 'register',
            'equipment' => Equipment::query()
                ->with(['branch', 'category', 'owner', 'defaultLocation', 'currentLocation', 'currentProject', 'currentSite', 'currentCustodian'])
                ->visibleTo($user)
                ->orderBy('asset_code')
                ->get()
                ->map(fn (Equipment $equipment): array => $this->equipmentRow($equipment, $canViewCosts)),
            'categories' => EquipmentCategory::query()->orderBy('name')->get()->map(fn (EquipmentCategory $category): array => $this->categoryRow($category)),
            'locations' => EquipmentLocation::query()->with(['branch', 'project', 'site'])->visibleTo($user)->orderBy('name')->get()->map(fn (EquipmentLocation $location): array => $this->locationRow($location)),
            'can' => [
                'create' => Gate::forUser($user)->allows('create', Equipment::class),
                'update' => $user->can('equipment.update'),
                'retire' => $user->can('equipment.retire'),
                'manageCategories' => Gate::forUser($user)->allows('create', EquipmentCategory::class),
                'manageLocations' => Gate::forUser($user)->allows('create', EquipmentLocation::class),
                'viewCosts' => $canViewCosts,
            ],
            ...$this->formOptions($user),
        ]);
    }

    public function show(Equipment $equipment): Response
    {
        Gate::authorize('view', $equipment);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $equipment->load(['branch', 'category', 'owner', 'defaultLocation', 'currentLocation', 'currentProject', 'currentSite', 'currentCustodian']);

        return Inertia::render('operations/equipment/show', [
            'equipment' => $this->equipmentRow($equipment, Gate::forUser($user)->allows('viewCosts', $equipment)),
            'documents' => $this->linkedDocumentsFor($equipment, $user),
            'categories' => EquipmentCategory::query()->orderBy('name')->get()->map(fn (EquipmentCategory $category): array => $this->categoryRow($category)),
            'locations' => EquipmentLocation::query()->with(['branch', 'project', 'site'])->visibleTo($user)->orderBy('name')->get()->map(fn (EquipmentLocation $location): array => $this->locationRow($location)),
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $equipment),
                'retire' => Gate::forUser($user)->allows('delete', $equipment),
                'uploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
                'viewCosts' => Gate::forUser($user)->allows('viewCosts', $equipment),
            ],
            ...$this->formOptions($user),
            ...$this->documentFormOptions($user),
        ]);
    }

    public function store(StoreEquipmentRequest $request, SaveEquipment $action): RedirectResponse
    {
        Gate::authorize('create', Equipment::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $equipment = $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment asset saved.']);

        return to_route('equipment.show', $equipment);
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment, SaveEquipment $action): RedirectResponse
    {
        Gate::authorize('update', $equipment);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $equipment);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment asset updated.']);

        return to_route('equipment.show', $equipment);
    }

    public function destroy(Equipment $equipment, SetEquipmentActiveStatus $action): RedirectResponse
    {
        Gate::authorize('delete', $equipment);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $restoring = ! $equipment->is_active;
        $action->handle($equipment, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => $restoring ? 'Equipment asset restored.' : 'Equipment asset retired.']);

        return to_route('equipment.index');
    }

    /** @return array<string, mixed> */
    private function formOptions(User $user): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user);

        return [
            'branches' => Branch::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->where('tenant_id', $tenantId)->visibleTo($user)->whereIn('branch_id', $branchIds)->orderBy('name')->get(['id', 'branch_id', 'reference', 'name']),
            'sites' => Site::query()->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds)->orderBy('name')->get(['id', 'branch_id', 'project_id', 'reference', 'name']),
            'staff' => Staff::query()->where('tenant_id', $tenantId)->where('status', 'active')->whereIn('branch_id', $branchIds)->orderBy('name')->get(['id', 'branch_id', 'name', 'staff_number']),
            'owners' => Customer::query()->where('tenant_id', $tenantId)->whereIn('type', ['supplier', 'subcontractor'])->where('status', 'active')->visibleTo($user)->orderBy('name')->get(['id', 'branch_id', 'name', 'type']),
            'currencies' => TenantCurrency::query()->with('currency')->where('tenant_id', $tenantId)->where('is_enabled', true)->get()->map(fn (TenantCurrency $currency): array => ['id' => $currency->currency_code, 'name' => sprintf('%s - %s', $currency->currency_code, $currency->currency->name)]),
        ];
    }

    /** @return array<string, mixed> */
    private function equipmentRow(Equipment $equipment, bool $canViewCosts): array
    {
        return [
            'id' => $equipment->id, 'branch_id' => $equipment->branch_id, 'branch_name' => $equipment->branch->name,
            'equipment_category_id' => $equipment->equipment_category_id, 'category_name' => $equipment->category->name,
            'asset_code' => $equipment->asset_code, 'name' => $equipment->name, 'make' => $equipment->make,
            'model' => $equipment->model, 'model_year' => $equipment->model_year, 'serial_number' => $equipment->serial_number,
            'registration_number' => $equipment->registration_number, 'chassis_number' => $equipment->chassis_number,
            'ownership_type' => $equipment->ownership_type, 'owner_customer_id' => $equipment->owner_customer_id,
            'owner_name' => $equipment->owner?->name ?? $equipment->owner_name, 'capacity_value' => $equipment->capacity_value,
            'capacity_unit' => $equipment->capacity_unit, 'acquired_on' => $equipment->acquired_on?->toDateString(),
            'acquisition_amount' => $canViewCosts ? $equipment->acquisition_amount : null,
            'acquisition_currency_code' => $canViewCosts ? $equipment->acquisition_currency_code : null,
            'hire_rate' => $canViewCosts ? $equipment->hire_rate : null, 'hire_rate_basis' => $canViewCosts ? $equipment->hire_rate_basis : null,
            'default_location_id' => $equipment->default_location_id, 'default_location_name' => $equipment->defaultLocation?->name,
            'meter_type' => $equipment->meter_type, 'starting_meter_reading' => $equipment->starting_meter_reading,
            'starting_meter_date' => $equipment->starting_meter_date?->toDateString(), 'fuel_efficiency_basis' => $equipment->fuel_efficiency_basis,
            'expected_fuel_efficiency' => $equipment->expected_fuel_efficiency, 'fuel_tolerance_percent' => $equipment->fuel_tolerance_percent,
            'tank_capacity' => $equipment->tank_capacity, 'current_status' => $equipment->current_status,
            'current_location_name' => $equipment->currentLocation?->name, 'current_project_name' => $equipment->currentProject?->name,
            'current_site_name' => $equipment->currentSite?->name, 'current_custodian_name' => $equipment->currentCustodian?->name,
            'current_meter_reading' => $equipment->current_meter_reading, 'current_meter_read_at' => $equipment->current_meter_read_at?->toDateTimeString(),
            'condition_summary' => $equipment->condition_summary, 'is_active' => $equipment->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function categoryRow(EquipmentCategory $category): array
    {
        return $category->only(['id', 'code', 'name', 'description', 'default_meter_type', 'default_capacity_unit', 'fuel_efficiency_basis', 'expected_fuel_efficiency', 'fuel_tolerance_percent', 'is_active']);
    }

    /** @return array<string, mixed> */
    private function locationRow(EquipmentLocation $location): array
    {
        return [
            ...$location->only(['id', 'branch_id', 'project_id', 'site_id', 'type', 'code', 'name', 'address', 'latitude', 'longitude', 'is_active']),
            'branch_name' => $location->branch->name, 'project_name' => $location->project?->name, 'site_name' => $location->site?->name,
        ];
    }
}
