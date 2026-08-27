<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\InventoryStore;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockMovementController
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('inventory.stock.view'), 403);
        $context = resolve(BranchContext::class);
        $branchIds = $context->accessibleBranchIds($actor);
        /** @var Collection<int, InventoryStockMovement> $movements */
        $movements = InventoryStockMovement::query()
            ->whereIn('branch_id', $branchIds)
            ->with(['store', 'item.stockUnit', 'postedBy'])
            ->latest('posted_at')
            ->limit(150)
            ->get();

        return Inertia::render('operations/inventory/movements', [
            'movements' => $movements->map(fn (InventoryStockMovement $movement): array => [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type->value,
                'quantity' => $movement->quantity,
                'reason' => $movement->reason,
                'posted_at' => $movement->posted_at->format('d M Y, H:i'),
                'posted_on' => $movement->posted_at->toDateString(),
                'status' => $movement->status->value,
                'source' => $this->sourceLabel($movement->source_type),
                'store' => $movement->store->only(['id', 'branch_id', 'name']),
                'item' => [
                    ...$movement->item->only(['id', 'name', 'code']),
                    'stock_unit' => $movement->item->stockUnit?->only(['id', 'name', 'symbol']),
                ],
                'posted_by' => $movement->postedBy->only(['id', 'name']),
            ]),
            'stores' => InventoryStore::query()->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name']),
            'can' => ['adjust' => $actor->can('inventory.stock.adjust'), 'transfer' => $actor->can('inventory.stock.transfer'), 'reverse' => $actor->can('inventory.stock.reverse')],
        ]);
    }

    private function sourceLabel(?string $sourceType): string
    {
        if ($sourceType === null) {
            return 'Manual movement';
        }

        if ($sourceType === 'store_transfer') {
            return 'Store transfer';
        }

        if ($sourceType === 'physical_stock_count') {
            return 'Physical stock count';
        }

        return match (class_basename($sourceType)) {
            'InventoryGoodsReceipt' => 'PO goods receipt',
            'MaterialRequisitionLine' => 'Material requisition',
            'InventoryTransfer' => 'Store transfer',
            'DailySiteReportMaterialLine' => 'Daily site report',
            default => str(class_basename($sourceType))->headline()->toString(),
        };
    }
}
