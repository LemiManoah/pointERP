<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryItem;
use App\Models\InventoryUnitConversion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class InventoryUnitConversionPermanentDeleteController
{
    public function __invoke(InventoryItem $inventoryItem, InventoryUnitConversion $inventoryUnitConversion, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $inventoryItem);
        abort_unless($inventoryUnitConversion->inventory_item_id === $inventoryItem->id, 404);
        throw_if($inventoryUnitConversion->is_active, ValidationException::withMessages(['conversion' => 'Deactivate the conversion before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $auditLogger->record('inventory.unit_conversion.permanently_deleted', $inventoryUnitConversion, $actor, $inventoryUnitConversion->toArray());
        $inventoryUnitConversion->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit conversion permanently deleted.']);

        return to_route('inventory.index', ['tab' => 'conversions', 'status' => 'inactive']);
    }
}
