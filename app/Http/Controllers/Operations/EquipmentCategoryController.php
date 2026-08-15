<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Equipment\SaveEquipmentCategory;
use App\Http\Requests\Operations\EquipmentCategories\StoreEquipmentCategoryRequest;
use App\Http\Requests\Operations\EquipmentCategories\UpdateEquipmentCategoryRequest;
use App\Models\EquipmentCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class EquipmentCategoryController
{
    public function store(StoreEquipmentCategoryRequest $request, SaveEquipmentCategory $action): RedirectResponse
    {
        Gate::authorize('create', EquipmentCategory::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment category saved.']);

        return to_route('equipment.index', ['tab' => 'categories']);
    }

    public function update(UpdateEquipmentCategoryRequest $request, EquipmentCategory $equipmentCategory, SaveEquipmentCategory $action): RedirectResponse
    {
        Gate::authorize('update', $equipmentCategory);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($request->validated(), $actor, $equipmentCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment category updated.']);

        return to_route('equipment.index', ['tab' => 'categories']);
    }

    public function destroy(EquipmentCategory $equipmentCategory, SaveEquipmentCategory $action): RedirectResponse
    {
        Gate::authorize('delete', $equipmentCategory);
        if ($equipmentCategory->is_active && $equipmentCategory->equipment()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category' => 'Move or retire active equipment before deactivating this category.']);
        }

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $data = $equipmentCategory->only(['code', 'name', 'description', 'default_meter_type', 'default_capacity_unit', 'fuel_efficiency_basis', 'expected_fuel_efficiency', 'fuel_tolerance_percent']);
        $action->handle([...$data, 'is_active' => ! $equipmentCategory->is_active], $actor, $equipmentCategory);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Equipment category status changed.']);

        return to_route('equipment.index', ['tab' => 'categories']);
    }
}
