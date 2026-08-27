<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\CancelMaterialRequisition;
use App\Actions\Operations\Inventory\SaveMaterialRequisition;
use App\Http\Requests\Operations\Inventory\SaveMaterialRequisitionRequest;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryReservation;
use App\Models\InventoryStore;
use App\Models\InventoryStockMovement;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\InventoryStockBalance;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class MaterialRequisitionController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MaterialRequisition::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $requisitions = MaterialRequisition::query()->visibleTo($actor)->with(['branch', 'store', 'requester', 'project', 'site'])->withCount('lines')->latest()->limit(200)->get();

        return Inertia::render('operations/inventory/requisitions/index', [
            'requisitions' => $requisitions->map(fn (MaterialRequisition $requisition): array => [
                'id' => $requisition->id,
                'reference' => $requisition->reference,
                'branch_name' => $requisition->branch->name,
                'store_name' => $requisition->store->name,
                'requester_name' => $requisition->requester->name,
                'project_name' => $requisition->project?->name,
                'site_name' => $requisition->site?->name,
                'required_by_date' => $requisition->required_by_date->toDateString(),
                'priority' => $requisition->priority->value,
                'status' => $requisition->status->value,
                'lines_count' => $requisition->lines_count,
            ]),
            ...$this->formOptions($actor),
            'canCreate' => Gate::forUser($actor)->allows('create', MaterialRequisition::class),
        ]);
    }

    public function show(MaterialRequisition $materialRequisition, InventoryStockBalance $balances): Response
    {
        Gate::authorize('view', $materialRequisition);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $materialRequisition->load(['branch', 'store', 'requester', 'project', 'site', 'approver', 'lines.item.stockUnit', 'lines.unit', 'lines.activity', 'lines.stockMovements.postedBy']);
        $lines = $materialRequisition->lines->sortBy('sort_order')->values()->map(function (MaterialRequisitionLine $line) use ($balances, $materialRequisition): array {
            $reservation = InventoryReservation::query()->where('source_type', MaterialRequisitionLine::class)->where('source_id', $line->id)->first();
            $balance = $line->item instanceof InventoryItem ? $balances->for($materialRequisition->store, $line->item) : null;
            $multiplier = BigDecimal::of((string) $line->conversion_multiplier);
            $outstandingStock = BigDecimal::of((string) $line->approved_quantity)->minus((string) $line->issued_quantity);

            $stockUnit = $line->item?->stockUnit;

            return [
                'id' => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'item_code' => $line->item_code_snapshot,
                'item_name' => $line->item_name_snapshot,
                'tracking_type' => $line->item?->tracking_type->value,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'project_activity_id' => $line->project_activity_id,
                'unit_name' => $line->unit_symbol_snapshot ?? $line->unit_code_snapshot,
                'stock_unit_name' => $stockUnit === null ? null : ($stockUnit->symbol ?? $stockUnit->code),
                'requested_quantity' => $line->requested_quantity,
                'stock_quantity' => $line->stock_quantity,
                'approved_quantity' => $line->approved_quantity,
                'issued_quantity' => $line->issued_quantity,
                'returned_quantity' => $line->returned_quantity,
                'outstanding_quantity' => (string) $outstandingStock->toScale(4),
                'outstanding_request_unit_quantity' => $multiplier->isZero() ? '0.0000' : (string) $outstandingStock->dividedBy($multiplier, 4, RoundingMode::Down),
                'purpose' => $line->purpose,
                'notes' => $line->notes,
                'available_stock' => $balance['available'] ?? null,
                'reserved_quantity' => $reservation?->reserved_quantity,
                'movements' => $line->stockMovements->map(fn (InventoryStockMovement $movement): array => ['id' => $movement->id, 'type' => $movement->movement_type->value, 'quantity' => $movement->quantity, 'original_quantity' => $movement->original_quantity, 'posted_at' => $movement->posted_at->toDateTimeString(), 'posted_by' => $movement->postedBy->name]),
            ];
        });

        return Inertia::render('operations/inventory/requisitions/show', [
            'requisition' => [
                'id' => $materialRequisition->id,
                'branch_id' => $materialRequisition->branch_id,
                'inventory_store_id' => $materialRequisition->inventory_store_id,
                'project_id' => $materialRequisition->project_id,
                'site_id' => $materialRequisition->site_id,
                'reference' => $materialRequisition->reference,
                'branch_name' => $materialRequisition->branch->name,
                'store_name' => $materialRequisition->store->name,
                'requester_name' => $materialRequisition->requester->name,
                'department' => $materialRequisition->department,
                'project_name' => $materialRequisition->project?->name,
                'site_name' => $materialRequisition->site?->name,
                'required_by_date' => $materialRequisition->required_by_date->toDateString(),
                'priority' => $materialRequisition->priority->value,
                'status' => $materialRequisition->status->value,
                'reason' => $materialRequisition->reason,
                'decision_reason' => $materialRequisition->decision_reason,
                'approved_by' => $materialRequisition->approver?->name,
                'lines' => $lines,
            ],
            'batches' => InventoryBatch::query()->where('inventory_store_id', $materialRequisition->inventory_store_id)->where('is_active', true)->get(['id', 'inventory_item_id', 'batch_number', 'expires_on']),
            'can' => [
                'update' => Gate::forUser($actor)->allows('update', $materialRequisition),
                'submit' => Gate::forUser($actor)->allows('submit', $materialRequisition),
                'approve' => Gate::forUser($actor)->allows('approve', $materialRequisition),
                'issue' => Gate::forUser($actor)->allows('issue', $materialRequisition),
                'returnStock' => Gate::forUser($actor)->allows('returnStock', $materialRequisition),
                'cancel' => Gate::forUser($actor)->allows('cancel', $materialRequisition),
            ],
            ...$this->formOptions($actor),
        ]);
    }

    public function store(SaveMaterialRequisitionRequest $request, SaveMaterialRequisition $action): RedirectResponse
    {
        Gate::authorize('create', MaterialRequisition::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $requisition = $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material requisition saved as a draft.']);

        return to_route('inventory.requisitions.show', $requisition);
    }

    public function update(SaveMaterialRequisitionRequest $request, MaterialRequisition $materialRequisition, SaveMaterialRequisition $action): RedirectResponse
    {
        Gate::authorize('update', $materialRequisition);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $materialRequisition);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material requisition updated.']);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }

    public function destroy(Request $request, MaterialRequisition $materialRequisition, CancelMaterialRequisition $action): RedirectResponse
    {
        Gate::authorize('cancel', $materialRequisition);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($materialRequisition, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material requisition cancelled and open reservations released.']);

        return to_route('inventory.requisitions.show', $materialRequisition);
    }

    /** @return array<string, mixed> */
    private function formOptions(User $actor): array
    {
        $context = resolve(BranchContext::class);
        $branchIds = $context->accessibleBranchIds($actor);
        $defaultBranch = $context->current($actor) ?? $context->operationalDefault($actor);
        abort_unless($defaultBranch instanceof Branch, 403);
        $projects = Project::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get()->filter(fn (Project $project): bool => Gate::forUser($actor)->allows('view', $project))->values();
        $projectIds = $projects->pluck('id')->all();
        $sites = Site::query()->whereIn('project_id', $projectIds)->where('status', 'active')->orderBy('name')->get()->filter(fn (Site $site): bool => Gate::forUser($actor)->allows('view', $site))->values();

        return [
            'branches' => $context->accessibleBranches($actor)->values(),
            'defaultBranchId' => $defaultBranch->id,
            'canChangeBranch' => $actor->can('inventory.stock.change-branch') && count($branchIds) > 1,
            'stores' => InventoryStore::query()->visibleTo($actor)->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'code']),
            'projects' => $projects->map(fn (Project $project): array => $project->only(['id', 'branch_id', 'name', 'reference'])),
            'sites' => $sites->map(fn (Site $site): array => $site->only(['id', 'branch_id', 'project_id', 'name', 'reference'])),
            'activities' => ProjectActivity::query()->whereIn('project_id', $projectIds)->where('status', 'active')->orderBy('name')->get(['id', 'project_id', 'name', 'code']),
            'items' => InventoryItem::query()->where('is_active', true)->with('stockUnit')->orderBy('name')->get()->map(fn (InventoryItem $item): array => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'stock_unit_id' => $item->stock_unit_id, 'stock_unit_name' => $item->stockUnit->symbol ?? $item->stockUnit->code]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', resolve(TenantContext::class)->id()))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'symbol']),
        ];
    }
}
