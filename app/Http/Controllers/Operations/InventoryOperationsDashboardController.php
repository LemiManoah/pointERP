<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\InventoryOperationsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryOperationsDashboardController
{
    public function __invoke(Request $request, InventoryOperationsReport $report): Response
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'uuid'], 'store_id' => ['nullable', 'uuid'], 'project_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'], 'item_id' => ['nullable', 'uuid'], 'category_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return Inertia::render('operations/inventory/dashboard', $report->dashboard($actor, $filters));
    }
}
