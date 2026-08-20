<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\DocumentLink;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryItemPermanentDeleteController
{
    public function __invoke(InventoryItem $inventoryItem, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $inventoryItem);
        throw_if($inventoryItem->is_active, ValidationException::withMessages(['item' => 'Deactivate the inventory item before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        if (DocumentLink::query()->where('linkable_type', InventoryItem::class)->where('linkable_id', $inventoryItem->id)->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This item cannot be permanently deleted because documents are linked to it.']);

            return to_route('inventory.index', ['status' => 'inactive']);
        }

        try {
            DB::transaction(function () use ($actor, $auditLogger, $inventoryItem): void {
                $auditLogger->record('inventory.item.permanently_deleted', $inventoryItem, $actor, $inventoryItem->toArray());
                $inventoryItem->forceDelete();
            });
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Inventory item permanently deleted.']);
        } catch (QueryException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This item cannot be permanently deleted because it has related inventory or operational records.']);
        }

        return to_route('inventory.index', ['status' => 'inactive']);
    }
}
