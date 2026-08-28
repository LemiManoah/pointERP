<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DsrMaterialReconciliationStatus;
use App\Enums\MaterialRequisitionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DailySiteReportMaterialLine;
use App\Models\DsrMaterialReconciliation;
use App\Models\InventoryCategory;
use App\Models\InventoryGoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\InventoryStoreItem;
use App\Models\MaterialRequisition;
use App\Models\MaterialRequisitionLine;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final readonly class InventoryOperationsReport
{
    public function __construct(private BranchContext $branchContext) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function dashboard(User $actor, array $input): array
    {
        $scope = $this->scope($actor, $input);
        $stockRows = $this->stockRows($actor, $scope);
        $requisitions = $this->requisitionRows($scope);
        $purchaseOrders = $this->purchaseOrderRows($scope);
        $rejectedReceipts = $this->receiptRows($scope)->filter(fn (array $row): bool => (float) $row['rejected_quantity'] > 0)->values();
        $dsrRows = $this->dsrRows($scope);

        $unfulfilled = $requisitions
            ->filter(fn (array $row): bool => in_array($row['status'], [MaterialRequisitionStatus::Submitted->value, MaterialRequisitionStatus::Approved->value, MaterialRequisitionStatus::PartiallyIssued->value], true))
            ->sortBy('required_by_date')
            ->take(8)
            ->values();
        $overdueOrders = $purchaseOrders
            ->filter(fn (array $row): bool => $row['is_overdue'])
            ->sortBy('expected_date')
            ->take(8)
            ->values();
        $unreconciled = $dsrRows
            ->filter(fn (array $row): bool => in_array($row['status'], [DsrMaterialReconciliationStatus::Pending->value, DsrMaterialReconciliationStatus::Partial->value, DsrMaterialReconciliationStatus::Exception->value, DsrMaterialReconciliationStatus::NotLinked->value], true))
            ->take(8)
            ->values();

        return [
            'filters' => $scope['selected'],
            'filterOptions' => $scope['options'],
            'metrics' => [
                'active_stores' => count($scope['store_ids']),
                'stocked_locations' => $stockRows->count(),
                'low_stock' => $stockRows->where('is_low_stock', true)->count(),
                'requisitions_awaiting_review' => $requisitions->where('status', MaterialRequisitionStatus::Submitted->value)->count(),
                'requisitions_awaiting_issue' => $requisitions->whereIn('status', [MaterialRequisitionStatus::Approved->value, MaterialRequisitionStatus::PartiallyIssued->value])->count(),
                'overdue_purchase_orders' => $purchaseOrders->where('is_overdue', true)->count(),
                'rejected_receipt_lines' => $rejectedReceipts->count(),
                'unreconciled_dsr_lines' => $unreconciled->count(),
            ],
            'lowStock' => $stockRows->where('is_low_stock', true)->sortBy('available')->take(8)->values(),
            'unfulfilledRequisitions' => $unfulfilled,
            'overduePurchaseOrders' => $overdueOrders,
            'rejectedReceipts' => $rejectedReceipts->take(8),
            'unreconciledMaterials' => $unreconciled,
            'canExport' => $actor->can('inventory.reports.export'),
            'canExportDsr' => $actor->can('inventory.dsr-reconciliation.export'),
            'canViewCosts' => $actor->can('inventory.purchase-orders.view-costs') || $actor->can('inventory.receipts.view-costs'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    public function export(string $report, User $actor, array $input): array
    {
        $scope = $this->scope($actor, $input);

        return match ($report) {
            'stock-balances' => $this->stockExport($actor, $scope),
            'movements' => $this->movementExport($scope),
            'requisitions' => $this->requisitionExport($scope),
            'purchase-orders' => $this->purchaseOrderExport($actor, $scope),
            'receipts' => $this->receiptExport($actor, $scope),
            'dsr-materials' => $this->dsrExport($scope),
            'supplier-performance' => $this->supplierPerformanceExport($scope),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{tenant_id: string, branch_ids: list<string>, store_ids: list<string>, selected: array<string, string|null>, options: array<string, mixed>}
     */
    private function scope(User $actor, array $input): array
    {
        $accessibleBranchIds = $this->branchContext->accessibleBranchIds($actor);
        $requestedBranch = is_string($input['branch_id'] ?? null) ? $input['branch_id'] : null;
        $effectiveBranch = $requestedBranch !== null && in_array($requestedBranch, $accessibleBranchIds, true)
            ? $requestedBranch
            : (count($accessibleBranchIds) === 1 ? $accessibleBranchIds[0] : null);
        $branchIds = $effectiveBranch !== null ? [$effectiveBranch] : $accessibleBranchIds;
        $stores = InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->with('branch')->orderBy('name')->get();
        $requestedStore = is_string($input['store_id'] ?? null) ? $input['store_id'] : null;
        $storeIds = $requestedStore !== null && $stores->contains('id', $requestedStore)
            ? [$requestedStore]
            : $stores->pluck('id')->all();
        $projectId = is_string($input['project_id'] ?? null) && $input['project_id'] !== '' ? $input['project_id'] : null;
        $supplierId = is_string($input['supplier_id'] ?? null) && $input['supplier_id'] !== '' ? $input['supplier_id'] : null;
        $itemId = is_string($input['item_id'] ?? null) && $input['item_id'] !== '' ? $input['item_id'] : null;
        $categoryId = is_string($input['category_id'] ?? null) && $input['category_id'] !== '' ? $input['category_id'] : null;
        $dateFrom = is_string($input['date_from'] ?? null) && $input['date_from'] !== '' ? $input['date_from'] : null;
        $dateTo = is_string($input['date_to'] ?? null) && $input['date_to'] !== '' ? $input['date_to'] : null;

        return [
            'tenant_id' => $actor->tenant_id,
            'branch_ids' => $branchIds,
            'store_ids' => array_values($storeIds),
            'selected' => ['branch_id' => $effectiveBranch, 'store_id' => $requestedStore, 'project_id' => $projectId, 'supplier_id' => $supplierId, 'item_id' => $itemId, 'category_id' => $categoryId, 'date_from' => $dateFrom, 'date_to' => $dateTo],
            'options' => [
                'branches' => Branch::query()->whereIn('id', $accessibleBranchIds)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
                'stores' => $stores->map(fn (InventoryStore $store): array => ['id' => $store->id, 'branch_id' => $store->branch_id, 'name' => $store->name, 'branch_name' => $store->branch->name])->values(),
                'projects' => Project::query()->whereIn('branch_id', $branchIds)->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'reference']),
                'suppliers' => Customer::query()->whereIn('type', [Customer::TYPE_SUPPLIER, Customer::TYPE_SUBCONTRACTOR])->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
                'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'inventory_category_id', 'name', 'code']),
                'categories' => InventoryCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function stockRows(User $actor, array $scope): Collection
    {
        $movementTotals = DB::table('inventory_stock_movements')->where('tenant_id', $actor->tenant_id)->whereIn('inventory_store_id', $scope['store_ids'])
            ->selectRaw('inventory_store_id, inventory_item_id, SUM(quantity) as quantity')->groupBy('inventory_store_id', 'inventory_item_id')->get()
            ->keyBy(fn (object $row): string => $row->inventory_store_id.':'.$row->inventory_item_id);
        $reservationTotals = DB::table('inventory_reservations')->where('tenant_id', $actor->tenant_id)->whereIn('inventory_store_id', $scope['store_ids'])
            ->whereIn('status', ['active', 'partially_issued'])->selectRaw('inventory_store_id, inventory_item_id, SUM(reserved_quantity - issued_quantity - released_quantity) as quantity')
            ->groupBy('inventory_store_id', 'inventory_item_id')->get()->keyBy(fn (object $row): string => $row->inventory_store_id.':'.$row->inventory_item_id);

        return InventoryStoreItem::query()->where('is_active', true)->whereIn('inventory_store_id', $scope['store_ids'])
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->with(['store.branch', 'item.stockUnit', 'item.category'])->get()
            ->map(function (InventoryStoreItem $setting) use ($movementTotals, $reservationTotals): array {
                $key = $setting->inventory_store_id.':'.$setting->inventory_item_id;
                $onHand = BigDecimal::of((string) ($movementTotals->get($key)->quantity ?? 0));
                $reserved = BigDecimal::of((string) ($reservationTotals->get($key)->quantity ?? 0));
                $available = $onHand->minus($reserved);
                $minimum = $setting->minimum_stock ?? $setting->item->minimum_stock;

                return [
                    'id' => $setting->id, 'item_id' => $setting->item->id, 'item_code' => $setting->item->code, 'item_name' => $setting->item->name,
                    'category' => $setting->item->category?->name, 'store_id' => $setting->store->id, 'store_name' => $setting->store->name,
                    'branch_name' => $setting->store->branch->name, 'unit' => $setting->item->stockUnit->symbol ?? $setting->item->stockUnit->name,
                    'on_hand' => (string) $onHand->toScale(4), 'reserved' => (string) $reserved->toScale(4), 'available' => (string) $available->toScale(4),
                    'minimum_stock' => $minimum, 'is_low_stock' => $minimum !== null && $available->isLessThanOrEqualTo((string) $minimum),
                ];
            })->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function requisitionRows(array $scope): Collection
    {
        return MaterialRequisition::query()->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
            ->when($scope['selected']['project_id'], fn (Builder $query, string $id): Builder => $query->where('project_id', $id))
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->whereHas('lines', fn (Builder $lineQuery): Builder => $lineQuery->where('inventory_item_id', $id)))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('lines.item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->when($scope['selected']['date_from'], fn (Builder $query, string $date): Builder => $query->whereDate('required_by_date', '>=', $date))
            ->when($scope['selected']['date_to'], fn (Builder $query, string $date): Builder => $query->whereDate('required_by_date', '<=', $date))
            ->with(['project', 'site', 'store', 'lines'])->latest('required_by_date')->get()->map(function (MaterialRequisition $requisition): array {
                $outstandingLines = $requisition->lines->filter(function (MaterialRequisitionLine $line): bool {
                    $outstanding = BigDecimal::of($line->approved_quantity)->minus($line->issued_quantity)->plus($line->returned_quantity);

                    return $outstanding->isGreaterThan(0);
                })->count();

                return [
                    'id' => $requisition->id, 'reference' => $requisition->reference, 'status' => $requisition->status->value,
                    'project' => $requisition->project?->name, 'site' => $requisition->site?->name, 'store' => $requisition->store->name,
                    'required_by_date' => $requisition->required_by_date->toDateString(), 'is_overdue' => $requisition->required_by_date->isPast() && ! in_array($requisition->status, [MaterialRequisitionStatus::Fulfilled, MaterialRequisitionStatus::Cancelled, MaterialRequisitionStatus::Rejected], true),
                    'lines_count' => $requisition->lines->count(), 'outstanding_lines' => $outstandingLines,
                ];
            })->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function purchaseOrderRows(array $scope): Collection
    {
        return PurchaseOrder::query()->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
            ->when($scope['selected']['supplier_id'], fn (Builder $query, string $id): Builder => $query->where('supplier_id', $id))
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->whereHas('lines', fn (Builder $lineQuery): Builder => $lineQuery->where('inventory_item_id', $id)))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('lines.item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->when($scope['selected']['date_from'], fn (Builder $query, string $date): Builder => $query->whereDate('order_date', '>=', $date))
            ->when($scope['selected']['date_to'], fn (Builder $query, string $date): Builder => $query->whereDate('order_date', '<=', $date))
            ->with(['supplier', 'store', 'lines'])->latest('order_date')->get()->map(function (PurchaseOrder $order): array {
                $outstandingLines = $order->lines->filter(fn (PurchaseOrderLine $line): bool => BigDecimal::of($line->outstandingQuantity())->isGreaterThan(0))->count();
                $isOpen = in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true);

                return [
                    'id' => $order->id, 'order_number' => $order->order_number, 'supplier' => $order->supplier_name_snapshot,
                    'store' => $order->store->name, 'status' => $order->status->value, 'order_date' => $order->order_date->toDateString(),
                    'expected_date' => $order->expected_date?->toDateString(), 'is_overdue' => $isOpen && $order->expected_date !== null && $order->expected_date->isPast() && $outstandingLines > 0,
                    'lines_count' => $order->lines->count(), 'outstanding_lines' => $outstandingLines,
                    'currency_code' => $order->currency_code, 'total_amount' => $order->total_amount,
                ];
            })->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function receiptRows(array $scope): Collection
    {
        return InventoryGoodsReceiptLine::query()
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->whereHas('receipt', fn (Builder $query): Builder => $query->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
                ->when($scope['selected']['supplier_id'], fn (Builder $supplierQuery, string $id): Builder => $supplierQuery->where('supplier_id', $id))
                ->when($scope['selected']['date_from'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('received_on', '>=', $date))
                ->when($scope['selected']['date_to'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('received_on', '<=', $date)))
            ->with(['receipt.supplier', 'receipt.purchaseOrder', 'receipt.store', 'item', 'unit'])->latest()->get()->map(fn (InventoryGoodsReceiptLine $line): array => [
                'id' => $line->id, 'receipt_id' => $line->receipt->id, 'receipt_reference' => $line->receipt->reference,
                'purchase_order_id' => $line->receipt->purchase_order_id, 'purchase_order' => $line->receipt->purchaseOrder->order_number,
                'supplier' => $line->receipt->supplier->name, 'store' => $line->receipt->store->name, 'received_on' => $line->receipt->received_on->toDateString(),
                'item_code' => $line->item->code, 'item_name' => $line->item->name, 'unit' => $line->unit->symbol ?? $line->unit->name,
                'delivered_quantity' => $line->quantity, 'accepted_quantity' => $line->accepted_quantity, 'rejected_quantity' => $line->rejected_quantity,
                'rejection_reason' => $line->rejection_reason, 'currency_code' => $line->receipt->currency_code, 'unit_cost' => $line->unit_cost, 'line_total' => $line->line_total,
            ])->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, covariant array<string, mixed>>
     */
    private function dsrRows(array $scope): Collection
    {
        return DailySiteReportMaterialLine::query()->whereIn('branch_id', $scope['branch_ids'])
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->whereHas('report', function (Builder $query) use ($scope): void {
                $query->where('status', 'approved')
                    ->when($scope['selected']['project_id'], fn (Builder $projectQuery, string $id): Builder => $projectQuery->where('project_id', $id))
                    ->when($scope['selected']['date_from'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('report_date', '>=', $date))
                    ->when($scope['selected']['date_to'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('report_date', '<=', $date));
            })
            ->with(['report.project', 'report.site', 'item.stockUnit', 'reconciliations'])->latest()->get()->map(function (DailySiteReportMaterialLine $line): array {
                $reported = (string) ($line->stock_unit_quantity ?? $line->quantity ?? '0');
                $allocated = (string) $line->reconciliations
                    ->reduce(fn (BigDecimal $total, DsrMaterialReconciliation $row): BigDecimal => $total->plus($row->allocated_quantity), BigDecimal::zero())
                    ->toScale(4);
                $direct = $this->sumReconciliationType($line->reconciliations, 'direct_issue');
                $external = $this->sumReconciliationType($line->reconciliations, 'external_non_stock');

                return [
                    'id' => $line->id, 'report_id' => $line->daily_site_report_id, 'report_reference' => $line->report->reference,
                    'report_date' => $line->report->report_date->toDateString(), 'project' => $line->report->project->name, 'site' => $line->report->site->name,
                    'item' => $line->material_name, 'unit' => $line->item?->stockUnit->symbol ?? $line->item?->stockUnit->name ?? $line->unit,
                    'status' => $line->inventory_reconciliation_status->value, 'reported_quantity' => $reported, 'allocated_quantity' => $allocated,
                    'direct_issue_quantity' => $direct, 'external_quantity' => $external,
                    'outstanding_quantity' => $this->nonNegativeDifference($reported, $allocated),
                ];
            })->values();
    }

    /** @param Collection<int, DsrMaterialReconciliation> $rows */
    private function sumReconciliationType(Collection $rows, string $type): string
    {
        return (string) $rows->filter(fn (DsrMaterialReconciliation $row): bool => $row->type->value === $type)
            ->reduce(fn (BigDecimal $total, DsrMaterialReconciliation $row): BigDecimal => $total->plus($row->allocated_quantity), BigDecimal::zero())->toScale(4);
    }

    private function nonNegativeDifference(string $left, string $right): string
    {
        $difference = BigDecimal::of($left)->minus($right);

        return (string) ($difference->isNegative() ? BigDecimal::zero() : $difference)->toScale(4);
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function stockExport(User $actor, array $scope): array
    {
        return ['filename' => 'stock-balances', 'headers' => ['Branch', 'Store', 'Item code', 'Item', 'Category', 'Unit', 'On hand', 'Reserved', 'Available', 'Minimum', 'Low stock'], 'rows' => $this->stockRows($actor, $scope)->map(fn (array $row): array => $this->exportRow([$row['branch_name'], $row['store_name'], $row['item_code'], $row['item_name'], $row['category'], $row['unit'], $row['on_hand'], $row['reserved'], $row['available'], $row['minimum_stock'], $row['is_low_stock'] ? 'Yes' : 'No']))->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function movementExport(array $scope): array
    {
        $rows = InventoryStockMovement::query()->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
            ->when($scope['selected']['project_id'], fn (Builder $query, string $id): Builder => $query->where('project_id', $id))
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->when($scope['selected']['date_from'], fn (Builder $query, string $date): Builder => $query->whereDate('posted_at', '>=', $date))
            ->when($scope['selected']['date_to'], fn (Builder $query, string $date): Builder => $query->whereDate('posted_at', '<=', $date))
            ->with(['store.branch', 'item.stockUnit', 'originalUnit', 'postedBy'])->oldest('posted_at')->get()->map(fn (InventoryStockMovement $movement): array => $this->exportRow([$movement->posted_at->toDateTimeString(), $movement->store->branch->name, $movement->store->name, $movement->item->code, $movement->item->name, $movement->movement_type->value, $movement->quantity, $movement->item->stockUnit->symbol ?? $movement->item->stockUnit->name, $movement->original_quantity, $movement->originalUnit->symbol ?? $movement->originalUnit->name, $movement->status->value, $movement->source_type, $movement->source_id, $movement->source_key, $movement->postedBy->name, $movement->reason]))->values()->all();

        return ['filename' => 'stock-movement-ledger', 'headers' => ['Posted at', 'Branch', 'Store', 'Item code', 'Item', 'Movement', 'Stock quantity', 'Stock unit', 'Original quantity', 'Original unit', 'Status', 'Source type', 'Source ID', 'Source key', 'Posted by', 'Reason'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function requisitionExport(array $scope): array
    {
        $rows = MaterialRequisitionLine::query()
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->whereHas('requisition', fn (Builder $query): Builder => $query->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
                ->when($scope['selected']['project_id'], fn (Builder $projectQuery, string $id): Builder => $projectQuery->where('project_id', $id))
                ->when($scope['selected']['date_from'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('required_by_date', '>=', $date))
                ->when($scope['selected']['date_to'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('required_by_date', '<=', $date)))
            ->with(['requisition.project', 'requisition.site', 'requisition.store'])->get()->map(function (MaterialRequisitionLine $line): array {
                $outstanding = $this->nonNegativeDifference($line->approved_quantity, (string) BigDecimal::of($line->issued_quantity)->minus($line->returned_quantity));

                return $this->exportRow([$line->requisition->reference, $line->requisition->status->value, $line->requisition->project?->name, $line->requisition->site?->name, $line->requisition->store->name, $line->requisition->required_by_date->toDateString(), $line->item_code_snapshot, $line->item_name_snapshot, $line->unit_symbol_snapshot, $line->stock_quantity, $line->approved_quantity, $line->issued_quantity, $line->returned_quantity, $outstanding]);
            })->values()->all();

        return ['filename' => 'requisition-fulfilment', 'headers' => ['Reference', 'Status', 'Project', 'Site', 'Store', 'Required by', 'Item code', 'Item', 'Stock unit', 'Requested', 'Approved', 'Issued', 'Returned', 'Outstanding'], 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function purchaseOrderExport(User $actor, array $scope): array
    {
        $canViewCosts = $actor->can('inventory.purchase-orders.view-costs');
        $headers = ['Order', 'Supplier', 'Store', 'Status', 'Order date', 'Expected date', 'Item code', 'Item', 'Stock unit', 'Ordered', 'Accepted', 'Rejected', 'Outstanding'];
        if ($canViewCosts) {
            $headers[] = 'Currency';
            $headers[] = 'Unit price';
            $headers[] = 'Line amount';
        }

        $rows = PurchaseOrderLine::query()
            ->when($scope['selected']['item_id'], fn (Builder $query, string $id): Builder => $query->where('inventory_item_id', $id))
            ->when($scope['selected']['category_id'], fn (Builder $query, string $id): Builder => $query->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('inventory_category_id', $id)))
            ->whereHas('purchaseOrder', fn (Builder $query): Builder => $query->whereIn('branch_id', $scope['branch_ids'])->whereIn('inventory_store_id', $scope['store_ids'])
                ->when($scope['selected']['supplier_id'], fn (Builder $supplierQuery, string $id): Builder => $supplierQuery->where('supplier_id', $id))
                ->when($scope['selected']['date_from'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('order_date', '>=', $date))
                ->when($scope['selected']['date_to'], fn (Builder $dateQuery, string $date): Builder => $dateQuery->whereDate('order_date', '<=', $date)))
            ->with(['purchaseOrder.store'])->get()->map(function (PurchaseOrderLine $line) use ($canViewCosts): array {
                $order = $line->purchaseOrder;
                $values = [$order->order_number, $order->supplier_name_snapshot, $order->store->name, $order->status->value, $order->order_date->toDateString(), (string) $order->expected_date?->toDateString(), $line->item_code_snapshot, $line->item_name_snapshot, $line->unit_symbol_snapshot, $line->stock_quantity, $line->accepted_quantity, $line->rejected_quantity, $line->outstandingQuantity()];
                if ($canViewCosts) {
                    $values[] = $order->currency_code;
                    $values[] = $line->unit_price;
                    $values[] = $line->line_amount;
                }

                return $this->exportRow($values);
            })->values()->all();

        return ['filename' => 'purchase-order-delivery', 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function receiptExport(User $actor, array $scope): array
    {
        $canViewCosts = $actor->can('inventory.receipts.view-costs');
        $headers = ['Receipt', 'PO', 'Supplier', 'Store', 'Received on', 'Item code', 'Item', 'Unit', 'Delivered', 'Accepted', 'Rejected', 'Rejection reason'];
        if ($canViewCosts) {
            $headers[] = 'Currency';
            $headers[] = 'Unit cost';
            $headers[] = 'Line total';
        }

        $rows = $this->receiptRows($scope)->map(function (array $row) use ($canViewCosts): array {
            $values = [$row['receipt_reference'], $row['purchase_order'], $row['supplier'], $row['store'], $row['received_on'], $row['item_code'], $row['item_name'], $row['unit'], $row['delivered_quantity'], $row['accepted_quantity'], $row['rejected_quantity'], (string) $row['rejection_reason']];
            if ($canViewCosts) {
                $values[] = $row['currency_code'];
                $values[] = (string) $row['unit_cost'];
                $values[] = (string) $row['line_total'];
            }

            return $this->exportRow($values);
        })->values()->all();

        return ['filename' => 'receipt-inspection', 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function dsrExport(array $scope): array
    {
        return ['filename' => 'dsr-material-reconciliation', 'headers' => ['Report', 'Date', 'Project', 'Site', 'Material', 'Unit', 'Status', 'Reported', 'Allocated', 'Direct issue', 'External', 'Outstanding'], 'rows' => $this->dsrRows($scope)->map(fn (array $row): array => $this->exportRow([$row['report_reference'], $row['report_date'], $row['project'], $row['site'], $row['item'], $row['unit'], $row['status'], $row['reported_quantity'], $row['allocated_quantity'], $row['direct_issue_quantity'], $row['external_quantity'], $row['outstanding_quantity']]))->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filename: string, headers: list<string>, rows: list<list<string>>}
     */
    private function supplierPerformanceExport(array $scope): array
    {
        $receipts = $this->receiptRows($scope);
        $rows = $this->purchaseOrderRows($scope)->groupBy('supplier')->map(fn (Collection $orders, string $supplier): array => $this->exportRow([$supplier, $orders->count(), $orders->where('is_overdue', true)->count(), $receipts->where('supplier', $supplier)->pluck('receipt_id')->unique()->count(), $receipts->where('supplier', $supplier)->filter(fn (array $row): bool => (float) $row['rejected_quantity'] > 0)->count()]))->values();

        return ['filename' => 'supplier-delivery-performance', 'headers' => ['Supplier', 'Purchase orders', 'Overdue orders', 'Receipts', 'Receipt lines with rejection'], 'rows' => $rows->all()];
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function exportRow(array $values): array
    {
        return array_map(static function (mixed $value): string {
            if ($value === null) {
                return '';
            }

            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }

            if (is_scalar($value)) {
                return (string) $value;
            }

            throw new UnexpectedValueException('Inventory report values must be scalar.');
        }, $values);
    }
}
