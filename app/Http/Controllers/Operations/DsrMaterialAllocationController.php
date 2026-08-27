<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReconcileDsrMaterialLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DsrMaterialAllocationController
{
    public function __invoke(Request $request, DailySiteReportMaterialLine $dailySiteReportMaterialLine, ReconcileDsrMaterialLine $action): RedirectResponse
    {
        Gate::authorize('manage', $dailySiteReportMaterialLine);
        $data = $request->validate(['inventory_stock_movement_id' => ['required', 'uuid'], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $movement = InventoryStockMovement::query()->findOrFail($data['inventory_stock_movement_id']);
        $action->allocate($dailySiteReportMaterialLine, $movement, (string) $data['quantity'], (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Existing stock issue allocated to the DSR material.']);

        return back();
    }
}
