<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\EquipmentLocation;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\InventoryUnitConversion;
use App\Models\Project;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($actor);
        $canViewCosts = Gate::allows('viewCosts', InventoryItem::class);
        $priceCurrency = $branchContext->current($actor)->default_currency_code;

        $items = InventoryItem::query()
            ->with(['category', 'stockUnit', 'preferredSupplier'])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'material_class' => $item->material_class->value,
                'tracking_type' => $item->tracking_type->value,
                'batch_number' => $item->batch_number,
                'is_expires' => $item->is_expires,
                'is_for_sale' => $item->is_for_sale,
                'inventory_category_id' => $item->inventory_category_id,
                'stock_unit_id' => $item->stock_unit_id,
                'category' => $item->category?->only(['id', 'name', 'description', 'is_active']),
                'stock_unit' => $item->stockUnit?->only(['id', 'code', 'name', 'symbol', 'quantity_dimension', 'is_base_unit', 'is_active']),
                'preferred_supplier' => $item->preferredSupplier?->only(['id', 'name', 'code', 'type']),
                'minimum_stock' => $item->minimum_stock,
                'reorder_quantity' => $item->reorder_quantity,
                'is_active' => $item->is_active,
                ...($canViewCosts ? [
                    'default_unit_cost' => $item->default_unit_cost,
                    'default_selling_price' => $item->default_selling_price,
                ] : []),
            ]);

        return Inertia::render('operations/inventory/index', [
            'activeTab' => (string) $request->string('tab', 'items'),
            'activeStatus' => (string) $request->string('status', 'active'),
            'categories' => InventoryCategory::query()->orderBy('name')->get(),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', resolve(TenantContext::class)->id()))->orderBy('name')->get(),
            'items' => $items,
            'priceLists' => $canViewCosts ? InventoryPriceTier::query()->withCount('prices')->orderBy('priority')->orderBy('name')->get() : [],
            'conversions' => InventoryUnitConversion::query()->with(['item.stockUnit', 'fromUnit', 'toUnit'])->orderByDesc('is_active')->get()->map(fn (InventoryUnitConversion $conversion): array => [
                'id' => $conversion->id, 'inventory_item_id' => $conversion->inventory_item_id,
                'item_name' => $conversion->item->name, 'item_code' => $conversion->item->code,
                'from_unit_id' => $conversion->from_unit_id, 'from_unit_name' => $conversion->fromUnit->name,
                'from_unit_symbol' => $conversion->fromUnit->symbol, 'to_unit_name' => $conversion->toUnit->name,
                'to_unit_symbol' => $conversion->toUnit->symbol, 'multiplier' => $conversion->multiplier,
                'effective_from' => $conversion->effective_from?->toDateString(), 'reason' => $conversion->reason, 'is_active' => $conversion->is_active,
            ]),
            'stores' => InventoryStore::query()->visibleTo($actor)->with(['branch', 'project', 'site'])->orderBy('name')->get(),
            'branches' => Branch::query()->whereIn('id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'projects' => Project::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'reference']),
            'sites' => Site::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'project_id', 'name', 'reference']),
            'locations' => EquipmentLocation::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'code']),
            'suppliers' => Customer::query()->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'type']),
            'priceCurrency' => $priceCurrency,
            'can' => [
                'manageItems' => Gate::allows('create', InventoryItem::class),
                'manageCategories' => Gate::allows('create', InventoryCategory::class),
                'manageUnits' => Gate::allows('create', UnitOfMeasure::class),
                'manageStores' => Gate::allows('create', InventoryStore::class),
                'managePriceLists' => Gate::allows('create', InventoryPriceTier::class),
                'viewCosts' => $canViewCosts,
                'permanentlyDeleteItems' => $actor->can('inventory.items.delete'),
                'permanentlyDeleteStores' => $actor->can('inventory.stores.delete'),
            ],
        ]);
    }
}
