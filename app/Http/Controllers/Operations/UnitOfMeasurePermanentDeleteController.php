<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class UnitOfMeasurePermanentDeleteController
{
    public function __invoke(UnitOfMeasure $unitOfMeasure, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('forceDelete', $unitOfMeasure);
        throw_if($unitOfMeasure->is_active, ValidationException::withMessages(['unit' => 'Deactivate the unit before permanently deleting it.']));
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        try {
            DB::transaction(function () use ($actor, $auditLogger, $unitOfMeasure): void {
                $auditLogger->record('inventory.unit.permanently_deleted', $unitOfMeasure, $actor, $unitOfMeasure->toArray());
                $unitOfMeasure->delete();
            });
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit permanently deleted.']);
        } catch (QueryException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'This unit cannot be deleted because inventory records use it.']);
        }

        return to_route('inventory.index', ['tab' => 'units', 'status' => 'inactive']);
    }
}
