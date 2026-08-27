<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\MaterialRequisitionPriority;
use App\Enums\MaterialRequisitionStatus;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\InventoryQuantityConverter;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveMaterialRequisition
{
    public function __construct(
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private InventoryQuantityConverter $converter,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?MaterialRequisition $requisition = null): MaterialRequisition
    {
        return DB::transaction(function () use ($actor, $data, $requisition): MaterialRequisition {
            $tenantId = $this->tenantContext->id();
            $branchId = (string) $data['branch_id'];
            if (! in_array($branchId, $this->branchContext->accessibleBranchIds($actor), true)) {
                throw ValidationException::withMessages(['branch_id' => 'Select a branch you can access.']);
            }

            $store = InventoryStore::query()->whereKey($data['inventory_store_id'])->where('branch_id', $branchId)->where('is_active', true)->first();
            if (! $store instanceof InventoryStore) {
                throw ValidationException::withMessages(['inventory_store_id' => 'Select an active store in the requisition branch.']);
            }

            $project = isset($data['project_id']) ? Project::query()->find($data['project_id']) : null;
            if ($project instanceof Project && ($project->branch_id !== $branchId || ! Gate::forUser($actor)->allows('view', $project))) {
                throw ValidationException::withMessages(['project_id' => 'Select a project you can access in this branch.']);
            }

            $site = isset($data['site_id']) ? Site::query()->find($data['site_id']) : null;
            if ($site instanceof Site && ($site->project_id !== $project?->id || ! Gate::forUser($actor)->allows('view', $site))) {
                throw ValidationException::withMessages(['site_id' => 'Select a site belonging to the selected project.']);
            }

            if ($requisition instanceof MaterialRequisition && ! $requisition->isEditable()) {
                throw ValidationException::withMessages(['requisition' => 'Only draft or returned requisitions can be edited.']);
            }

            $oldValues = $requisition?->toArray() ?? [];
            $requisition ??= new MaterialRequisition;
            $requisition->fill([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'inventory_store_id' => $store->id,
                'requesting_user_id' => $requisition->exists ? $requisition->requesting_user_id : $actor->id,
                'project_id' => $project?->id,
                'site_id' => $site?->id,
                'reference' => $requisition->exists ? $requisition->reference : $this->reference(),
                'department' => $data['department'] ?? null,
                'required_by_date' => $data['required_by_date'],
                'priority' => MaterialRequisitionPriority::from((string) $data['priority']),
                'status' => $requisition->exists ? $requisition->status : MaterialRequisitionStatus::Draft,
                'reason' => $data['reason'],
                'updated_by' => $actor->id,
            ]);
            if (! $requisition->exists) {
                $requisition->created_by = $actor->id;
            }
            $requisition->save();

            $requisition->lines()->delete();
            foreach ((array) $data['lines'] as $index => $lineData) {
                if (! is_array($lineData)) {
                    continue;
                }
                $this->createLine($requisition, $lineData, (int) $index);
            }

            $this->auditLogger->record(
                $oldValues === [] ? 'inventory.requisition.created' : 'inventory.requisition.updated',
                $requisition,
                $actor,
                $oldValues,
                $requisition->refresh()->load('lines')->toArray(),
                (string) $requisition->reason,
                $store->branch,
            );

            return $requisition;
        });
    }

    /** @param array<string, mixed> $data */
    private function createLine(MaterialRequisition $requisition, array $data, int $index): void
    {
        $item = isset($data['inventory_item_id']) ? InventoryItem::query()->where('is_active', true)->find($data['inventory_item_id']) : null;
        $unit = UnitOfMeasure::query()->where('is_active', true)->findOrFail($data['unit_of_measure_id']);
        $activity = isset($data['project_activity_id']) ? ProjectActivity::query()->find($data['project_activity_id']) : null;
        if ($activity instanceof ProjectActivity && $activity->project_id !== $requisition->project_id) {
            throw ValidationException::withMessages(["lines.$index.project_activity_id" => 'The activity must belong to the selected project.']);
        }

        $multiplier = $item instanceof InventoryItem ? $this->converter->multiplier($item, $unit->id) : BigDecimal::one();
        $requested = BigDecimal::of((string) $data['requested_quantity']);
        MaterialRequisitionLine::query()->create([
            'tenant_id' => $requisition->tenant_id,
            'material_requisition_id' => $requisition->id,
            'inventory_item_id' => $item?->id,
            'unit_of_measure_id' => $unit->id,
            'project_activity_id' => $activity?->id,
            'item_code_snapshot' => $item?->code,
            'item_name_snapshot' => $item instanceof InventoryItem ? $item->name : (string) $data['description'],
            'unit_code_snapshot' => $unit->code,
            'unit_symbol_snapshot' => $unit->symbol,
            'requested_quantity' => (string) $requested->toScale(4, RoundingMode::HalfUp),
            'conversion_multiplier' => (string) $multiplier->toScale(10),
            'stock_quantity' => (string) $requested->multipliedBy($multiplier)->toScale(4, RoundingMode::HalfUp),
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sort_order' => $index,
        ]);
    }

    private function reference(): string
    {
        do {
            $reference = 'MR-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (MaterialRequisition::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
