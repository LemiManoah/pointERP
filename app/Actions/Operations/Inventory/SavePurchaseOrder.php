<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\BranchCurrency;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryQuantityConverter;
use App\Services\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SavePurchaseOrder
{
    public function __construct(private TenantContext $tenantContext, private InventoryQuantityConverter $converter, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?PurchaseOrder $purchaseOrder = null): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $data, $purchaseOrder): PurchaseOrder {
            $supplier = Customer::query()->findOrFail($data['supplier_id']);
            $store = InventoryStore::query()->where('branch_id', $data['branch_id'])->where('is_active', true)->findOrFail($data['inventory_store_id']);
            if ($supplier->branch_id !== null && $supplier->branch_id !== $store->branch_id) {
                throw ValidationException::withMessages(['supplier_id' => 'Select a supplier available to this branch.']);
            }

            if ($data['currency_code'] !== $store->branch->default_currency_code || ! BranchCurrency::query()->where('branch_id', $store->branch_id)->where('currency_code', $data['currency_code'])->where('is_enabled', true)->exists()) {
                throw ValidationException::withMessages(['currency_code' => 'Purchase orders currently use the receiving branch default currency.']);
            }

            if ($purchaseOrder instanceof PurchaseOrder && ! $purchaseOrder->isEditable()) {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft or returned purchase orders can be edited.']);
            }

            /** @var list<array<string, mixed>> $lines */
            $lines = $data['lines'];
            $canManageCosts = $actor->can('inventory.purchase-orders.view-costs');
            $oldValues = $purchaseOrder?->load('lines')->toArray() ?? [];
            $purchaseOrder ??= new PurchaseOrder;
            $purchaseOrder->fill(['tenant_id' => $this->tenantContext->id(), 'branch_id' => $data['branch_id'], 'inventory_store_id' => $store->id, 'supplier_id' => $supplier->id, 'order_number' => $purchaseOrder->exists ? $purchaseOrder->order_number : $this->number(), 'supplier_name_snapshot' => $supplier->name, 'supplier_code_snapshot' => $supplier->code, 'order_date' => $data['order_date'], 'expected_date' => $data['expected_date'] ?? null, 'currency_code' => $data['currency_code'], 'status' => $purchaseOrder->exists ? $purchaseOrder->status : PurchaseOrderStatus::Draft, 'discount_amount' => $canManageCosts ? ($data['discount_amount'] ?? 0) : 0, 'tax_amount' => $canManageCosts ? ($data['tax_amount'] ?? 0) : 0, 'delivery_terms' => $data['delivery_terms'] ?? null, 'payment_terms' => $data['payment_terms'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_by' => $actor->id]);
            if (! $purchaseOrder->exists) {
                $purchaseOrder->created_by = $actor->id;
            }

            $purchaseOrder->save();
            $purchaseOrder->lines()->delete();
            $subtotal = BigDecimal::zero();
            foreach ($lines as $index => $lineData) {
                $subtotal = $subtotal->plus($this->createLine($purchaseOrder, $lineData, $index, $actor));
            }

            $discount = BigDecimal::of($canManageCosts ? (string) ($data['discount_amount'] ?? 0) : '0');
            $tax = BigDecimal::of($canManageCosts ? (string) ($data['tax_amount'] ?? 0) : '0');
            if ($discount->isGreaterThan($subtotal)) {
                throw ValidationException::withMessages(['discount_amount' => 'Discount cannot exceed the order subtotal.']);
            }

            $purchaseOrder->forceFill(['subtotal' => (string) $subtotal->toScale(4), 'total_amount' => (string) $subtotal->minus($discount)->plus($tax)->toScale(4, RoundingMode::HalfUp)])->save();
            $this->auditLogger->record($oldValues === [] ? 'inventory.purchase_order.created' : 'inventory.purchase_order.updated', $purchaseOrder, $actor, $oldValues, $purchaseOrder->refresh()->load('lines')->toArray(), $purchaseOrder->notes, $store->branch);

            return $purchaseOrder;
        });
    }

    /** @param array<string, mixed> $data */
    private function createLine(PurchaseOrder $purchaseOrder, array $data, int $index, User $actor): BigDecimal
    {
        $item = InventoryItem::query()->where('is_active', true)->findOrFail($data['inventory_item_id']);
        $unit = UnitOfMeasure::query()->findOrFail($data['unit_of_measure_id']);
        $multiplier = $this->converter->multiplier($item, $unit->id);
        $quantity = BigDecimal::of((string) $data['ordered_quantity']);
        $stockQuantity = $quantity->multipliedBy($multiplier);
        $recordedPrice = BigDecimal::of((string) ($item->default_unit_cost ?? 0))->multipliedBy($multiplier);
        $requestedPrice = BigDecimal::of((string) $data['unit_price']);
        $canOverridePrice = $actor->can('inventory.purchase-orders.override-price');
        if ($item->default_unit_cost === null && ! $canOverridePrice) {
            throw ValidationException::withMessages([sprintf('lines.%d.unit_price', $index) => 'Record a default purchase cost for this item before ordering it.']);
        }

        $price = $canOverridePrice ? $requestedPrice : $recordedPrice;
        $priceSource = $canOverridePrice && ! $requestedPrice->isEqualTo($recordedPrice) ? 'manual_override' : 'recorded_cost';
        $amount = $quantity->multipliedBy($price);
        PurchaseOrderLine::query()->create(['tenant_id' => $purchaseOrder->tenant_id, 'purchase_order_id' => $purchaseOrder->id, 'inventory_item_id' => $item->id, 'unit_of_measure_id' => $unit->id, 'item_code_snapshot' => $item->code, 'item_name_snapshot' => $item->name, 'unit_code_snapshot' => $unit->code, 'unit_symbol_snapshot' => $unit->symbol, 'ordered_quantity' => (string) $quantity->toScale(4), 'conversion_multiplier' => (string) $multiplier->toScale(10), 'stock_quantity' => (string) $stockQuantity->toScale(4), 'unit_price' => (string) $price->toScale(4), 'price_source' => $priceSource, 'line_amount' => (string) $amount->toScale(4, RoundingMode::HalfUp), 'sort_order' => $index]);

        return $amount;
    }

    private function number(): string
    {
        do {
            $number = 'PO-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (PurchaseOrder::query()->where('order_number', $number)->exists());

        return $number;
    }
}
