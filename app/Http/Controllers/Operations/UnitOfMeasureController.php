<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\SaveUnitOfMeasure;
use App\Http\Requests\Operations\Inventory\StoreUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
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
}
