<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventoryUnitConversion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class ProcurementFormOptions
{
    public function __construct(private BranchContext $branchContext, private TenantContext $tenantContext) {}

    /** @return array<string, mixed> */
    public function for(User $actor): array
    {
        $branchIds = $this->branchContext->accessibleBranchIds($actor);
        $defaultBranch = $this->branchContext->current($actor) ?? $this->branchContext->operationalDefault($actor);
        abort_unless($defaultBranch instanceof Branch, 403);
        $canViewCosts = $actor->can('inventory.purchase-orders.view-costs');

        return [
            'branches' => $this->branchContext->accessibleBranches($actor)->values(),
            'defaultBranchId' => $defaultBranch->id,
            'canChangeBranch' => $actor->can('inventory.stock.change-branch') && count($branchIds) > 1,
            'stores' => InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'code']),
            'suppliers' => Customer::query()->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'code']),
            'items' => InventoryItem::query()->where('is_active', true)->with('conversions')->orderBy('name')->get(['id', 'name', 'code', 'stock_unit_id', 'preferred_supplier_id', 'default_unit_cost'])->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'stock_unit_id' => $item->stock_unit_id,
                'preferred_supplier_id' => $item->preferred_supplier_id,
                'default_unit_cost' => $canViewCosts ? $item->default_unit_cost : null,
                'conversions' => $item->conversions->where('is_active', true)->map(fn (InventoryUnitConversion $conversion): array => [
                    'unit_id' => $conversion->from_unit_id,
                    'multiplier' => $conversion->multiplier,
                    'divisor' => $conversion->divisor,
                ])->values()->all(),
            ]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantContext->id()))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'symbol']),
            'canOverridePrice' => $actor->can('inventory.purchase-orders.override-price'),
            'canViewCosts' => $canViewCosts,
        ];
    }
}
