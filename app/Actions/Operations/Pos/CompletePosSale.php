<?php

declare(strict_types=1);

namespace App\Actions\Operations\Pos;

use App\Actions\Operations\Inventory\PostInventoryStockMovement;
use App\Enums\InventoryBatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryTrackingType;
use App\Enums\PosPaymentMethod;
use App\Enums\PosPaymentStatus;
use App\Enums\PosSaleStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\PosSaleLine;
use App\Models\PosSaleLineAllocation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BranchContext;
use App\Services\InventoryQuantityConverter;
use App\Services\InventoryStockBalance;
use App\Services\PosPriceResolver;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type PosPaymentData array{method: string, amount: string, reference?: string|null}
 */
final readonly class CompletePosSale
{
    public function __construct(
        private TenantContext $tenantContext,
        private BranchContext $branchContext,
        private InventoryQuantityConverter $converter,
        private InventoryStockBalance $balances,
        private PosPriceResolver $prices,
        private PostInventoryStockMovement $postMovement,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): PosSale
    {
        $existing = PosSale::query()->where('checkout_key', (string) $data['checkout_key'])->first();
        if ($existing instanceof PosSale) {
            return $existing;
        }

        $branch = $this->branch((string) $data['branch_id'], $actor);
        $store = InventoryStore::query()->whereKey((string) $data['inventory_store_id'])->where('branch_id', $branch->id)->where('is_active', true)->firstOrFail();
        $tier = InventoryPriceTier::query()->whereKey((string) $data['inventory_price_tier_id'])->where('is_active', true)->firstOrFail();
        $customer = isset($data['customer_id']) ? Customer::query()->whereKey((string) $data['customer_id'])->where('status', 'active')->firstOrFail() : null;
        $prepared = $this->prepareLines($data['lines'], $store, $tier, $branch, $actor);
        /** @var list<PosPaymentData> $payments */
        $payments = $data['payments'];
        $subtotal = collect($prepared)->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['gross']), BigDecimal::zero());
        $discount = collect($prepared)->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['discount']), BigDecimal::zero());
        $total = $subtotal->minus($discount);
        $this->validatePayments($payments, $total);

        return DB::transaction(function () use ($actor, $branch, $customer, $data, $discount, $payments, $prepared, $store, $subtotal, $tier, $total): PosSale {
            $sale = PosSale::query()->create([
                'tenant_id' => $this->tenantContext->id(), 'branch_id' => $branch->id, 'inventory_store_id' => $store->id,
                'customer_id' => $customer?->id, 'inventory_price_tier_id' => $tier->id, 'sale_number' => $this->number('POS'), 'checkout_key' => $data['checkout_key'],
                'status' => PosSaleStatus::Draft, 'currency_code' => $branch->default_currency_code,
                'subtotal' => (string) $subtotal->toScale(4), 'discount_total' => (string) $discount->toScale(4), 'total_amount' => (string) $total->toScale(4),
                'notes' => $data['notes'] ?? null, 'sold_by' => $actor->id,
            ]);

            foreach ($prepared as $index => $preparedLine) {
                $line = PosSaleLine::query()->create([
                    'tenant_id' => $this->tenantContext->id(), 'pos_sale_id' => $sale->id,
                    'inventory_item_id' => $preparedLine['item']->id, 'unit_of_measure_id' => $preparedLine['unit']->id,
                    'inventory_item_price_id' => $preparedLine['price']['id'], 'quantity' => (string) $preparedLine['quantity']->toScale(4),
                    'conversion_multiplier' => (string) $preparedLine['multiplier']->toScale(10), 'stock_quantity' => (string) $preparedLine['stock_quantity']->toScale(4),
                    'unit_price' => $preparedLine['price']['amount'], 'discount_amount' => (string) $preparedLine['discount']->toScale(4),
                    'line_total' => (string) $preparedLine['gross']->minus($preparedLine['discount'])->toScale(4),
                    'item_code_snapshot' => $preparedLine['item']->code, 'item_name_snapshot' => $preparedLine['item']->name,
                    'unit_symbol_snapshot' => $preparedLine['unit']->symbol ?? $preparedLine['unit']->name, 'price_list_snapshot' => $tier->name,
                    'sort_order' => $index,
                ]);
                $this->postAllocations($sale, $line, $store, $preparedLine['item'], $preparedLine['stock_quantity'], $actor);
            }

            foreach ($payments as $payment) {
                PosPayment::query()->create([
                    'tenant_id' => $this->tenantContext->id(), 'branch_id' => $branch->id, 'pos_sale_id' => $sale->id,
                    'payment_number' => $this->number('PAY'), 'method' => PosPaymentMethod::from((string) $payment['method']),
                    'amount' => (string) BigDecimal::of((string) $payment['amount'])->toScale(4), 'currency_code' => $branch->default_currency_code,
                    'reference' => $payment['reference'] ?? null, 'status' => PosPaymentStatus::Recorded,
                    'recorded_by' => $actor->id, 'recorded_at' => now(),
                ]);
            }

            $sale->update(['status' => PosSaleStatus::Completed, 'completed_by' => $actor->id, 'completed_at' => now()]);
            $this->auditLogger->record('pos.sale.completed', $sale, $actor, [], $sale->fresh()?->toArray() ?? [], branch: $branch);

            return $sale->fresh(['lines.allocations', 'payments']) ?? $sale;
        });
    }

    private function branch(string $id, User $actor): Branch
    {
        $branch = $this->branchContext->accessibleBranches($actor)->firstWhere('id', $id);
        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages(['branch_id' => 'You do not have access to the selected branch.']);
        }

        return $branch;
    }

    /**
     * @return list<array{
     *     item: InventoryItem,
     *     unit: UnitOfMeasure,
     *     quantity: BigDecimal,
     *     multiplier: BigDecimal,
     *     stock_quantity: BigDecimal,
     *     price: array{id: string|null, amount: string},
     *     gross: BigDecimal,
     *     discount: BigDecimal
     * }>
     */
    private function prepareLines(mixed $lines, InventoryStore $store, InventoryPriceTier $tier, Branch $branch, User $actor): array
    {
        if (! is_array($lines)) {
            throw ValidationException::withMessages(['lines' => 'Add at least one item to the cart.']);
        }

        /** @var list<array{inventory_item_id: string, unit_of_measure_id: string, quantity: string, discount_amount: string}> $lines */
        $prepared = [];
        foreach ($lines as $index => $data) {
            $item = InventoryItem::query()->whereKey((string) $data['inventory_item_id'])->where('is_active', true)->where('is_for_sale', true)->firstOrFail();
            if ($item->tracking_type === InventoryTrackingType::Serial) {
                throw ValidationException::withMessages([sprintf('lines.%d.inventory_item_id', $index) => 'Serial-tracked items are not available in POS yet.']);
            }

            $enabled = $item->storeSettings()->where('inventory_store_id', $store->id)->where('is_active', true)->exists();
            if (! $enabled) {
                throw ValidationException::withMessages([sprintf('lines.%d.inventory_item_id', $index) => $item->name.' is not enabled in the selected store.']);
            }

            $unit = UnitOfMeasure::query()->findOrFail((string) $data['unit_of_measure_id']);
            $quantity = BigDecimal::of((string) $data['quantity']);
            $multiplier = $this->converter->multiplier($item, $unit->id);
            $stockQuantity = $quantity->multipliedBy($multiplier);
            $price = $this->prices->resolve($item, $tier, $branch, $unit->id);
            $gross = $quantity->multipliedBy($price['amount'])->toScale(4, RoundingMode::HalfUp);
            $discount = BigDecimal::of((string) $data['discount_amount'])->toScale(4, RoundingMode::HalfUp);
            if ($discount->isPositive() && ! $actor->can('pos.apply-discount')) {
                throw ValidationException::withMessages([sprintf('lines.%d.discount_amount', $index) => 'You do not have permission to apply discounts.']);
            }

            if ($discount->isGreaterThan($gross)) {
                throw ValidationException::withMessages([sprintf('lines.%d.discount_amount', $index) => 'The discount cannot exceed the line value.']);
            }

            $prepared[] = ['item' => $item, 'unit' => $unit, 'quantity' => $quantity, 'multiplier' => $multiplier, 'stock_quantity' => $stockQuantity, 'price' => $price, 'gross' => $gross, 'discount' => $discount];
        }

        return $prepared;
    }

    private function postAllocations(PosSale $sale, PosSaleLine $line, InventoryStore $store, InventoryItem $item, BigDecimal $required, User $actor): void
    {
        if ($item->tracking_type !== InventoryTrackingType::Batch) {
            $this->postAllocation($sale, $line, $store, $item, null, $required, $actor);

            return;
        }

        $remaining = $required;
        $batches = InventoryBatch::query()->where('inventory_item_id', $item->id)->where('inventory_store_id', $store->id)->where('status', InventoryBatchStatus::Available->value)->where('is_active', true)->where(fn (Builder $query): Builder => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))->orderByRaw('expires_on IS NULL')->oldest('expires_on')->oldest()->get();
        foreach ($batches as $batch) {
            if ($remaining->isZero()) {
                break;
            }

            $available = BigDecimal::of($this->balances->forBatch($store, $item, $batch->id));
            if (! $available->isPositive()) {
                continue;
            }

            $allocated = $available->isLessThan($remaining) ? $available : $remaining;
            $this->postAllocation($sale, $line, $store, $item, $batch, $allocated, $actor);
            $remaining = $remaining->minus($allocated);
        }

        if ($remaining->isPositive()) {
            throw ValidationException::withMessages(['lines' => 'Not enough valid batch stock is available for '.$item->name.'.']);
        }
    }

    private function postAllocation(PosSale $sale, PosSaleLine $line, InventoryStore $store, InventoryItem $item, ?InventoryBatch $batch, BigDecimal $quantity, User $actor): void
    {
        $allocation = PosSaleLineAllocation::query()->create(['tenant_id' => $this->tenantContext->id(), 'pos_sale_line_id' => $line->id, 'inventory_batch_id' => $batch?->id, 'stock_quantity' => (string) $quantity->toScale(4), 'batch_number_snapshot' => $batch?->batch_number, 'expires_on_snapshot' => $batch?->expires_on?->toDateString()]);
        $movement = $this->postMovement->handle($store, $item, ['movement_type' => InventoryMovementType::Issue->value, 'original_quantity' => (string) $quantity->toScale(4), 'original_unit_id' => $item->stock_unit_id, 'conversion_multiplier' => '1', 'inventory_batch_id' => $batch?->id, 'source_type' => PosSaleLineAllocation::class, 'source_id' => $allocation->id, 'source_key' => 'pos-sale:'.$allocation->id, 'reason' => 'POS sale '.$sale->sale_number], $actor);
        $allocation->update(['inventory_stock_movement_id' => $movement->id]);
    }

    /** @param list<PosPaymentData> $payments */
    private function validatePayments(array $payments, BigDecimal $total): void
    {
        $paid = BigDecimal::zero();
        foreach ($payments as $index => $payment) {
            $method = PosPaymentMethod::from((string) $payment['method']);
            if ($method !== PosPaymentMethod::Cash && mb_trim((string) ($payment['reference'] ?? '')) === '') {
                throw ValidationException::withMessages([sprintf('payments.%d.reference', $index) => 'Enter a reference for non-cash payments.']);
            }

            $paid = $paid->plus((string) $payment['amount']);
        }

        if (! $paid->isEqualTo($total)) {
            throw ValidationException::withMessages(['payments' => 'Payments must equal the sale total of '.$total->toScale(4).'.']);
        }
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(5));
    }
}
