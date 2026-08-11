<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\DailySiteReports\CreateDailySiteReportCorrection;
use App\Http\Requests\Operations\DailySiteReports\StoreDailySiteReportCorrectionRequest;
use App\Models\DailySiteReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class DailySiteReportCorrectionController
{
    public function store(StoreDailySiteReportCorrectionRequest $request, DailySiteReport $dailySiteReport, CreateDailySiteReportCorrection $action): RedirectResponse
    {
        Gate::authorize('correct', $dailySiteReport);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array<string, mixed> $changes */
        $changes = $request->validated('changes');
        $newValues = array_filter($changes, filled(...));

        if ($newValues === []) {
            throw ValidationException::withMessages([
                'changes' => 'Enter at least one proposed correction value.',
            ]);
        }

        $action->handle(
            report: $dailySiteReport,
            actor: $actor,
            reason: (string) $request->validated('reason'),
            newValues: $newValues,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Correction request recorded.']);

        return to_route('daily-site-reports.show', $dailySiteReport);
    }
}
