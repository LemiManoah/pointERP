<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveInventoryItem;
use App\Http\Requests\Operations\Inventory\StoreInventoryItemRequest;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryItemPrice;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\InventoryUnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\TenantContext;
use App\Support\Operations\PresentsLinkedDocuments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryItemController
{
    use PresentsLinkedDocuments;

    public function store(StoreInventoryItemRequest $request, SaveInventoryItem $action): RedirectResponse
    {
        Gate::authorize('create', InventoryItem::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item saved.']);

        return to_route('inventory.index');
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $inventoryItem, SaveInventoryItem $action): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $inventoryItem);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item updated.']);

        return to_route('inventory.index');
    }

    public function show(InventoryItem $inventoryItem): Response
    {
        Gate::authorize('view', $inventoryItem);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $canViewCosts = Gate::allows('viewCosts', InventoryItem::class);
        $branchContext = resolve(BranchContext::class);
        $tenant = resolve(TenantContext::class)->current();
        $branchIds = $branchContext->accessibleBranchIds($actor);
        $inventoryItem->load(['category', 'stockUnit', 'preferredSupplier', 'conversions.fromUnit', 'conversions.toUnit', 'prices.tier', 'prices.unit', 'prices.branch', 'batches.store', 'storeSettings.store.branch']);

        return Inertia::render('operations/inventory/show', [
            'item' => [
                'id' => $inventoryItem->id,
                'code' => $inventoryItem->code,
                'name' => $inventoryItem->name,
                'description' => $inventoryItem->description,
                'material_class' => $inventoryItem->material_class->value,
                'tracking_type' => $inventoryItem->tracking_type->value,
                'batch_number' => $inventoryItem->batch_number,
                'is_expires' => $inventoryItem->is_expires,
                'is_for_sale' => $inventoryItem->is_for_sale,
                'minimum_stock' => $inventoryItem->minimum_stock,
                'reorder_quantity' => $inventoryItem->reorder_quantity,
                'default_unit_cost' => $canViewCosts ? $inventoryItem->default_unit_cost : null,
                'default_selling_price' => $canViewCosts ? $inventoryItem->default_selling_price : null,
                'is_active' => $inventoryItem->is_active,
                'category' => $inventoryItem->category?->only(['id', 'name']),
                'stock_unit' => $inventoryItem->stockUnit?->only(['id', 'name', 'symbol']),
                'preferred_supplier' => $inventoryItem->preferredSupplier?->only(['id', 'name']),
            ],
            'conversions' => $inventoryItem->conversions->map(fn (InventoryUnitConversion $conversion): array => [
                'id' => $conversion->id,
                'from_unit_id' => $conversion->from_unit_id,
                'from_unit' => $conversion->fromUnit?->only(['id', 'name', 'symbol']),
                'to_unit' => $conversion->toUnit?->only(['id', 'name', 'symbol']),
                'multiplier' => $conversion->multiplier,
                'effective_from' => $conversion->effective_from?->toDateString(),
                'reason' => $conversion->reason,
                'is_active' => $conversion->is_active,
            ]),
            'prices' => $canViewCosts ? $inventoryItem->prices->map(fn (InventoryItemPrice $price): array => [
                'id' => $price->id,
                'tier_code' => $price->tier->code,
                'tier_name' => $price->tier->name,
                'branch_id' => $price->branch_id,
                'unit_of_measure_id' => $price->unit_of_measure_id,
                'unit' => $price->unit->only(['id', 'name', 'symbol']),
                'branch_name' => $price->branch?->name ?? 'Facility-wide',
                'currency' => $price->branch?->default_currency_code ?? $tenant->default_currency_code,
                'amount' => $price->amount,
                'minimum_quantity' => $price->minimum_quantity,
                'effective_from' => $price->effective_from?->toDateString(),
                'effective_until' => $price->effective_until?->toDateString(),
                'is_active' => $price->is_active,
            ]) : [],
            'batches' => $inventoryItem->batches->map(fn (InventoryBatch $batch): array => [
                'id' => $batch->id,
                'inventory_store_id' => $batch->inventory_store_id,
                'store_name' => $batch->store?->name,
                'batch_number' => $batch->batch_number,
                'manufactured_on' => $batch->manufactured_on?->toDateString(),
                'expires_on' => $batch->expires_on?->toDateString(),
                'status' => $batch->status->value,
                'notes' => $batch->notes,
                'is_active' => $batch->is_active,
            ]),
            'storeSettings' => $inventoryItem->storeSettings->map(fn (InventoryStoreItem $setting): array => [
                'id' => $setting->id,
                'inventory_store_id' => $setting->inventory_store_id,
                'store_name' => $setting->store->name,
                'branch_name' => $setting->store->branch->name,
                'minimum_stock' => $setting->minimum_stock,
                'reorder_quantity' => $setting->reorder_quantity,
                'storage_location' => $setting->storage_location,
                'is_active' => $setting->is_active,
            ]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))->where('is_active', true)->orderBy('name')->get()->map(fn (UnitOfMeasure $unit): array => ['id' => $unit->id, 'name' => $unit->name, 'code' => $unit->code, 'symbol' => $unit->symbol]),
            'stores' => InventoryStore::query()->visibleTo($actor)->where('is_active', true)->with('branch')->orderBy('name')->get()->map(fn (InventoryStore $store): array => ['id' => $store->id, 'name' => $store->name, 'code' => $store->code, 'branch_name' => $store->branch->name]),
            'branches' => Branch::query()->whereIn('id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'default_currency_code']),
            'documents' => $this->linkedDocumentsFor($inventoryItem, $actor),
            ...$this->documentFormOptions($actor),
            'can' => [
                'manage' => Gate::allows('update', $inventoryItem),
                'permanentlyDelete' => Gate::allows('forceDelete', $inventoryItem),
                'viewCosts' => $canViewCosts,
                'uploadDocuments' => $actor->can('documents.upload'),
            ],
        ]);
    }

    public function destroy(InventoryItem $inventoryItem, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('update', $inventoryItem);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $inventoryItem->is_active];
        $inventoryItem->update(['is_active' => ! $inventoryItem->is_active, 'updated_by' => $actor->id]);
        $auditLogger->record('inventory.item.status_changed', $inventoryItem, $actor, $old, ['is_active' => $inventoryItem->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $inventoryItem->is_active ? 'Inventory item restored.' : 'Inventory item deactivated.']);

        return to_route('inventory.index');
    }
}
