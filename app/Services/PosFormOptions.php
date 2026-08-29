<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryTrackingType;
use App\Enums\PosPaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PosFormOptions
{
    public function __construct(
        private BranchContext $branchContext,
        private InventoryStockBalance $balances,
        private InventoryQuantityConverter $converter,
        private PosPriceResolver $prices,
    ) {}

    /** @return array<string, mixed> */
    public function for(Request $request, User $user): array
    {
        $branches = $this->branchContext->accessibleBranches($user);
        $branch = $this->branch($request, $user, $branches);
        $stores = InventoryStore::query()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get();
        $store = $this->store($request, $stores);
        $tiers = InventoryPriceTier::query()->where('is_active', true)->orderBy('priority')->orderBy('name')->get();
        $tier = $this->tier($request, $tiers);

        return [
            'branches' => $branches->map(fn (Branch $row): array => ['value' => $row->id, 'label' => $row->name, 'currency_code' => $row->default_currency_code])->values(),
            'stores' => $stores->map(fn (InventoryStore $row): array => ['value' => $row->id, 'label' => $row->name])->values(),
            'priceLists' => $tiers->map(fn (InventoryPriceTier $row): array => ['value' => $row->id, 'label' => $row->name])->values(),
            'customers' => Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code'])->map(fn (Customer $row): array => ['value' => $row->id, 'label' => $row->name, 'description' => $row->code])->values(),
            'paymentMethods' => collect(PosPaymentMethod::cases())->map(fn (PosPaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
            'checkoutKey' => Str::uuid()->toString(),
            'selected' => ['branch_id' => $branch->id, 'store_id' => $store?->id, 'price_list_id' => $tier?->id, 'currency_code' => $branch->default_currency_code],
            'can' => ['changeBranch' => $user->can('pos.change-branch') && $branches->count() > 1, 'changeStore' => $user->can('pos.change-store') && $stores->count() > 1, 'changePriceList' => $user->can('pos.change-price-list') && $tiers->count() > 1, 'discount' => $user->can('pos.apply-discount')],
            'items' => $store instanceof InventoryStore && $tier instanceof InventoryPriceTier ? $this->items($store, $tier, $branch) : [],
        ];
    }

    /** @param Collection<int, Branch> $branches */
    private function branch(Request $request, User $user, Collection $branches): Branch
    {
        $requested = $request->string('branch_id')->toString();
        $branch = $requested !== '' && $user->can('pos.change-branch') ? $branches->firstWhere('id', $requested) : null;
        $branch ??= $this->branchContext->current($user) ?? $this->branchContext->operationalDefault($user);

        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => 'Select an accessible branch before using POS.']);
        }

        return $branch;
    }

    /** @param Collection<int, InventoryStore> $stores */
    private function store(Request $request, Collection $stores): ?InventoryStore
    {
        $requested = $request->string('store_id')->toString();

        return ($requested !== '' ? $stores->firstWhere('id', $requested) : null) ?? $stores->first();
    }

    /** @param Collection<int, InventoryPriceTier> $tiers */
    private function tier(Request $request, Collection $tiers): ?InventoryPriceTier
    {
        $requested = $request->string('price_list_id')->toString();

        return ($requested !== '' ? $tiers->firstWhere('id', $requested) : null)
            ?? $tiers->firstWhere('code', 'RETAIL')
            ?? $tiers->first();
    }

    /** @return list<array<string, mixed>> */
    private function items(InventoryStore $store, InventoryPriceTier $tier, Branch $branch): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->where('is_for_sale', true)
            ->where('tracking_type', '!=', InventoryTrackingType::Serial->value)
            ->whereHas('storeSettings', fn (Builder $query): Builder => $query->where('inventory_store_id', $store->id)->where('is_active', true))
            ->with(['category', 'stockUnit', 'conversions.fromUnit'])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item): ?array => $this->item($item, $store, $tier, $branch))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function item(InventoryItem $item, InventoryStore $store, InventoryPriceTier $tier, Branch $branch): ?array
    {
        $units = collect([['id' => $item->stockUnit->id, 'label' => $item->stockUnit->name, 'symbol' => $item->stockUnit->symbol ?? $item->stockUnit->name]])
            ->merge($item->conversions->where('is_active', true)->map(fn (InventoryUnitConversion $conversion): array => ['id' => $conversion->fromUnit->id, 'label' => $conversion->fromUnit->name, 'symbol' => $conversion->fromUnit->symbol ?? $conversion->fromUnit->name]))
            ->unique('id')
            ->map(function (array $unit) use ($branch, $item, $store, $tier): ?array {
                try {
                    $price = $this->prices->resolve($item, $tier, $branch, $unit['id']);
                    $multiplier = $this->converter->multiplier($item, $unit['id']);
                    $available = BigDecimal::of($this->balances->for($store, $item)['available'])->dividedBy($multiplier, 4, RoundingMode::Down);

                    return [...$unit, 'price_id' => $price['id'], 'price' => $price['amount'], 'multiplier' => (string) $multiplier->toScale(10), 'available' => (string) $available->toScale(4)];
                } catch (ValidationException) {
                    return null;
                }
            })->filter()->values();

        if ($units->isEmpty()) {
            return null;
        }

        return ['id' => $item->id, 'code' => $item->code, 'name' => $item->name, 'category' => $item->category->name, 'tracking_type' => $item->tracking_type->value, 'units' => $units->all()];
    }
}
