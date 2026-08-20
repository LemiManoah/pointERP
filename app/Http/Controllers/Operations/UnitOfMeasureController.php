<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveUnitOfMeasure;
use App\Http\Requests\Operations\Inventory\StoreUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class UnitOfMeasureController
{
    public function store(StoreUnitOfMeasureRequest $request, SaveUnitOfMeasure $action): RedirectResponse
    {
        Gate::authorize('create', UnitOfMeasure::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit of measure saved.']);

        return to_route('inventory.index', ['tab' => 'units']);
    }

    public function update(StoreUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure, SaveUnitOfMeasure $action): RedirectResponse
    {
        Gate::authorize('update', $unitOfMeasure);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $unitOfMeasure);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit of measure updated.']);

        return to_route('inventory.index', ['tab' => 'units']);
    }

    public function destroy(UnitOfMeasure $unitOfMeasure, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $unitOfMeasure);
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $old = ['is_active' => $unitOfMeasure->is_active];
        $unitOfMeasure->update(['is_active' => ! $unitOfMeasure->is_active]);
        $auditLogger->record('inventory.unit.status_changed', $unitOfMeasure, $actor, $old, ['is_active' => $unitOfMeasure->is_active]);
        Inertia::flash('toast', ['type' => 'success', 'message' => $unitOfMeasure->is_active ? 'Unit restored.' : 'Unit deactivated.']);

        return to_route('inventory.index', ['tab' => 'units']);
    }
}
