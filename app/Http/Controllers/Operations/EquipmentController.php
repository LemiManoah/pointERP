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
use App\Models\EquipmentAssignment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentFuelTransaction;
use App\Models\EquipmentLocation;
use App\Models\EquipmentLocationConfirmation;
use App\Models\EquipmentMeterReading;
use App\Models\EquipmentTransfer;
use App\Models\Project;
use App\Models\Site;
use App\Models\Staff;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
        $hasOpenTransfer = $equipment->transfers()
            ->whereIn('status', [EquipmentTransfer::STATUS_REQUESTED, EquipmentTransfer::STATUS_APPROVED, EquipmentTransfer::STATUS_DISPATCHED])
            ->exists();
        $canViewCosts = Gate::forUser($user)->allows('viewCosts', $equipment);

        return Inertia::render('operations/equipment/show', [
            'activeTab' => request()->string('tab')->value() ?: 'overview',
            'equipment' => $this->equipmentRow($equipment, $canViewCosts),
            'meterReadings' => EquipmentMeterReading::query()
                ->with(['recordedBy', 'approvedBy', 'rejectedBy', 'correctedReading'])
                ->where('equipment_id', $equipment->id)
                ->latest('read_at')
                ->latest('created_at')
                ->get()
                ->map(fn (EquipmentMeterReading $reading): array => [
                    'id' => $reading->id,
                    'event_type' => $reading->event_type,
                    'reading_value' => $reading->reading_value,
                    'read_at' => $reading->read_at->toDateTimeString(),
                    'previous_reading' => $reading->previous_reading,
                    'usage' => $reading->usage,
                    'status' => $reading->status,
                    'corrects_reading_id' => $reading->corrects_reading_id,
                    'corrected_value' => $reading->correctedReading?->reading_value,
                    'reason' => $reading->reason,
                    'evidence_note' => $reading->evidence_note,
                    'decision_note' => $reading->decision_note,
                    'recorded_by' => $reading->recordedBy?->name,
                    'approved_by' => $reading->approvedBy?->name,
                    'rejected_by' => $reading->rejectedBy?->name,
                    'can_correct' => Gate::forUser($user)->allows('correct', $reading)
                        && $reading->status === EquipmentMeterReading::STATUS_ACCEPTED
                        && $reading->event_type !== 'correction'
                        && ! $reading->corrections()->where('status', EquipmentMeterReading::STATUS_PENDING)->exists(),
                    'can_approve' => Gate::forUser($user)->allows('approveCorrection', $reading)
                        && $reading->status === EquipmentMeterReading::STATUS_PENDING
                        && $reading->event_type === 'correction',
                ]),
            'assignments' => EquipmentAssignment::query()
                ->with(['project', 'site', 'location', 'returnLocation', 'custodian', 'handedOverBy', 'acceptedReturnBy'])
                ->where('equipment_id', $equipment->id)
                ->latest('assigned_at')
                ->get()
                ->map(fn (EquipmentAssignment $assignment): array => $this->assignmentRow($assignment, $user)),
            'transfers' => EquipmentTransfer::query()
                ->with(['sourceBranch', 'destinationBranch', 'sourceLocation', 'destinationLocation', 'destinationProject', 'destinationSite', 'requestedBy'])
                ->where('equipment_id', $equipment->id)
                ->latest('requested_at')
                ->get()
                ->map(fn (EquipmentTransfer $transfer): array => $this->transferRow($transfer, $user)),
            'locationConfirmations' => EquipmentLocationConfirmation::query()
                ->with(['location', 'confirmedBy'])
                ->where('equipment_id', $equipment->id)
                ->latest('observed_at')
                ->get()
                ->map(fn (EquipmentLocationConfirmation $confirmation): array => [
                    'id' => $confirmation->id,
                    'location_name' => $confirmation->location->name,
                    'observed_at' => $confirmation->observed_at->toDateTimeString(),
                    'observed_status' => $confirmation->observed_status,
                    'condition_observation' => $confirmation->condition_observation,
                    'note' => $confirmation->note,
                    'confirmed_by' => $confirmation->confirmedBy->name,
                ]),
            'fuelTransactions' => EquipmentFuelTransaction::query()
                ->with(['provider', 'receiver', 'submittedBy', 'approvedBy'])
                ->where('equipment_id', $equipment->id)
                ->latest('transacted_at')
                ->latest('created_at')
                ->get()
                ->map(fn (EquipmentFuelTransaction $transaction): array => $this->fuelTransactionRow($transaction, $user, $canViewCosts)),
            'documents' => $this->linkedDocumentsFor($equipment, $user),
            'categories' => EquipmentCategory::query()->orderBy('name')->get()->map(fn (EquipmentCategory $category): array => $this->categoryRow($category)),
            'locations' => EquipmentLocation::query()->with(['branch', 'project', 'site'])->visibleTo($user)->orderBy('name')->get()->map(fn (EquipmentLocation $location): array => $this->locationRow($location)),
            'can' => [
                'update' => Gate::forUser($user)->allows('update', $equipment),
                'retire' => Gate::forUser($user)->allows('delete', $equipment),
                'uploadDocuments' => Gate::forUser($user)->allows('create', Document::class),
                'viewCosts' => $canViewCosts,
                'recordFuel' => Gate::forUser($user)->allows('create', EquipmentFuelTransaction::class)
                    && $equipment->is_active
                    && ! in_array($equipment->current_status, ['retired', 'transferred'], true),
                'recordReading' => Gate::forUser($user)->allows('create', [EquipmentMeterReading::class, $equipment]),
                'assign' => Gate::forUser($user)->allows('create', EquipmentAssignment::class)
                    && $equipment->is_active
                    && in_array($equipment->current_status, ['available', 'idle'], true)
                    && ! $hasOpenTransfer,
                'requestTransfer' => Gate::forUser($user)->allows('create', EquipmentTransfer::class)
                    && $equipment->is_active
                    && in_array($equipment->current_status, ['available', 'idle'], true)
                    && ! $hasOpenTransfer,
                'confirmLocation' => Gate::forUser($user)->allows('create', EquipmentLocationConfirmation::class)
                    && $equipment->is_active
                    && ! in_array($equipment->current_status, ['retired', 'transferred'], true),
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
        try {
            $action->handle($equipment, $actor);
        } catch (ValidationException $validationException) {
            $error = $validationException->errors()['equipment'][0] ?? null;
            $message = is_string($error) ? $error : 'This equipment cannot be retired while an operational workflow is open.';
            Inertia::flash('toast', ['type' => 'warning', 'message' => $message]);

            return back();
        }

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
        $owner = $equipment->getRelation('owner');

        return [
            'id' => $equipment->id, 'branch_id' => $equipment->branch_id, 'branch_name' => $equipment->branch->name,
            'equipment_category_id' => $equipment->equipment_category_id, 'category_name' => $equipment->category->name,
            'asset_code' => $equipment->asset_code, 'name' => $equipment->name, 'make' => $equipment->make,
            'model' => $equipment->model, 'model_year' => $equipment->model_year, 'serial_number' => $equipment->serial_number,
            'registration_number' => $equipment->registration_number, 'chassis_number' => $equipment->chassis_number,
            'ownership_type' => $equipment->ownership_type, 'owner_customer_id' => $equipment->owner_customer_id,
            'owner_name' => $owner instanceof Customer ? $owner->name : $equipment->owner_name, 'capacity_value' => $equipment->capacity_value,
            'capacity_unit' => $equipment->capacity_unit, 'acquired_on' => $equipment->acquired_on?->toDateString(),
            'acquisition_amount' => $canViewCosts ? $equipment->acquisition_amount : null,
            'acquisition_currency_code' => $canViewCosts ? $equipment->acquisition_currency_code : null,
            'hire_rate' => $canViewCosts ? $equipment->hire_rate : null, 'hire_rate_basis' => $canViewCosts ? $equipment->hire_rate_basis : null,
            'default_location_id' => $equipment->default_location_id, 'default_location_name' => $equipment->defaultLocation?->name,
            'meter_type' => $equipment->meter_type, 'starting_meter_reading' => $equipment->starting_meter_reading,
            'starting_meter_date' => $equipment->starting_meter_date?->toDateString(), 'fuel_efficiency_basis' => $equipment->fuel_efficiency_basis,
            'expected_fuel_efficiency' => $equipment->expected_fuel_efficiency, 'fuel_tolerance_percent' => $equipment->fuel_tolerance_percent,
            'tank_capacity' => $equipment->tank_capacity, 'current_status' => $equipment->current_status,
            'current_location_id' => $equipment->current_location_id,
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

    /** @return array<string, mixed> */
    private function assignmentRow(EquipmentAssignment $assignment, User $user): array
    {
        $custodian = $assignment->getRelation('custodian');

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'project_name' => $assignment->project->name,
            'site_name' => $assignment->site->name,
            'location_name' => $assignment->location->name,
            'return_location_name' => $assignment->returnLocation?->name,
            'custodian_name' => $custodian instanceof Staff ? $custodian->name : $assignment->external_custodian_name,
            'custodian_employer' => $assignment->external_custodian_employer,
            'assigned_at' => $assignment->assigned_at->toDateTimeString(),
            'expected_return_at' => $assignment->expected_return_at?->toDateTimeString(),
            'returned_at' => $assignment->returned_at?->toDateTimeString(),
            'handover_meter_reading' => $assignment->handover_meter_reading,
            'return_meter_reading' => $assignment->return_meter_reading,
            'handover_condition' => $assignment->handover_condition,
            'return_condition' => $assignment->return_condition,
            'assignment_notes' => $assignment->assignment_notes,
            'return_notes' => $assignment->return_notes,
            'handed_over_by' => $assignment->handedOverBy->name,
            'accepted_return_by' => $assignment->acceptedReturnBy?->name,
            'can_return' => Gate::forUser($user)->allows('update', $assignment),
        ];
    }

    /** @return array<string, mixed> */
    private function transferRow(EquipmentTransfer $transfer, User $user): array
    {
        $sourceLocation = $transfer->getRelation('sourceLocation');

        return [
            'id' => $transfer->id,
            'status' => $transfer->status,
            'source_branch_name' => $transfer->sourceBranch->name,
            'source_location_name' => $sourceLocation instanceof EquipmentLocation ? $sourceLocation->name : 'Unconfirmed source',
            'destination_branch_name' => $transfer->destinationBranch->name,
            'destination_location_name' => $transfer->destinationLocation->name,
            'destination_project_name' => $transfer->destinationProject?->name,
            'destination_site_name' => $transfer->destinationSite?->name,
            'reason' => $transfer->reason,
            'transport_reference' => $transfer->transport_reference,
            'requested_at' => $transfer->requested_at->toDateTimeString(),
            'approved_at' => $transfer->approved_at?->toDateTimeString(),
            'dispatched_at' => $transfer->dispatched_at?->toDateTimeString(),
            'received_at' => $transfer->received_at?->toDateTimeString(),
            'dispatch_meter_reading' => $transfer->dispatch_meter_reading,
            'receipt_meter_reading' => $transfer->receipt_meter_reading,
            'dispatch_condition' => $transfer->dispatch_condition,
            'receipt_condition' => $transfer->receipt_condition,
            'requested_by' => $transfer->requestedBy->name,
            'can_approve' => Gate::forUser($user)->allows('approve', $transfer),
            'can_dispatch' => Gate::forUser($user)->allows('dispatch', $transfer),
            'can_receive' => Gate::forUser($user)->allows('receive', $transfer),
        ];
    }

    /** @return array<string, mixed> */
    private function fuelTransactionRow(EquipmentFuelTransaction $transaction, User $user, bool $canViewCosts): array
    {
        $provider = $transaction->getRelation('provider');
        $receiver = $transaction->getRelation('receiver');

        return [
            'id' => $transaction->id,
            'transacted_at' => $transaction->transacted_at->toDateTimeString(),
            'transaction_type' => $transaction->transaction_type,
            'fuel_type' => $transaction->fuel_type,
            'quantity' => $transaction->quantity,
            'unit' => $transaction->unit,
            'source_type' => $transaction->source_type,
            'source_name' => $provider instanceof Customer ? $provider->name : $transaction->source_name,
            'receiver_name' => $receiver instanceof Staff ? $receiver->name : null,
            'unit_cost' => $canViewCosts ? $transaction->unit_cost : null,
            'total_cost' => $canViewCosts ? $transaction->total_cost : null,
            'currency_code' => $canViewCosts ? $transaction->currency_code : null,
            'meter_reading' => $transaction->meter_reading,
            'tank_level_before' => $transaction->tank_level_before,
            'tank_level_after' => $transaction->tank_level_after,
            'is_full_tank' => $transaction->is_full_tank,
            'voucher_reference' => $transaction->voucher_reference,
            'notes' => $transaction->notes,
            'exception_status' => $transaction->exception_status,
            'exception_reason' => $transaction->exception_reason,
            'status' => $transaction->status,
            'reversal_of_id' => $transaction->reversal_of_id,
            'reversal_reason' => $transaction->reversal_reason,
            'submitted_by' => $transaction->submittedBy->name,
            'approved_by' => $transaction->approvedBy?->name,
            'can_approve' => Gate::forUser($user)->allows('approve', $transaction),
            'can_reverse' => Gate::forUser($user)->allows('reverse', $transaction)
                && ! $transaction->reversals()->exists(),
        ];
    }
}
