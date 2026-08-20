<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InventoryStockExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($actor->can('inventory.reports.export'), 403);
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($actor);
        $movements = InventoryStockMovement::query()->whereIn('branch_id', $branchIds)->with(['store', 'item.stockUnit', 'originalUnit', 'postedBy'])->oldest('posted_at')->get();

        return response()->streamDownload(function () use ($movements): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Posted at', 'Store', 'Item code', 'Item', 'Movement', 'Stock quantity', 'Stock unit', 'Original quantity', 'Original unit', 'Status', 'Posted by', 'Reason'], escape: '\\');
            foreach ($movements as $movement) {
                fputcsv($handle, [$movement->posted_at->toDateTimeString(), $movement->store->name, $movement->item->code, $movement->item->name, $movement->movement_type->value, $movement->quantity, $movement->item->stockUnit->symbol ?? $movement->item->stockUnit->name, $movement->original_quantity, $movement->originalUnit->symbol ?? $movement->originalUnit->name, $movement->status->value, $movement->postedBy->name, $movement->reason], escape: '\\');
            }

            fclose($handle);
        }, 'inventory-stock-ledger-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
