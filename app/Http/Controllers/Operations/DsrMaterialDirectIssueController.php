<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Inventory\ReconcileDsrMaterialLine;
use App\Http\Requests\Operations\Inventory\StoreDsrMaterialDirectIssueRequest;
use App\Models\DailySiteReportMaterialLine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class DsrMaterialDirectIssueController
{
    public function __invoke(StoreDsrMaterialDirectIssueRequest $request, DailySiteReportMaterialLine $dailySiteReportMaterialLine, ReconcileDsrMaterialLine $action): RedirectResponse
    {
        Gate::authorize('directIssue', $dailySiteReportMaterialLine);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->directIssue($dailySiteReportMaterialLine, $request->validated(), $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unmatched DSR material issued from stock.']);

        return back();
    }
}
