<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReconcileDsrMaterialLine;
use App\Models\DailySiteReportMaterialLine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DsrMaterialExternalController
{
    public function __invoke(Request $request, DailySiteReportMaterialLine $dailySiteReportMaterialLine, ReconcileDsrMaterialLine $action): RedirectResponse
    {
        Gate::authorize('markExternal', $dailySiteReportMaterialLine);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->markExternal($dailySiteReportMaterialLine, (string) $data['reason'], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Material classified as external. No stock was deducted.']);

        return back();
    }
}
