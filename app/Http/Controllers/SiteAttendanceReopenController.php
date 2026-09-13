<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workforce\ReopenSiteAttendance;
use App\Http\Requests\Operations\Workforce\ReopenSiteAttendanceRequest;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class SiteAttendanceReopenController
{
    public function __invoke(ReopenSiteAttendanceRequest $request, SiteAttendanceRegister $siteAttendanceRegister, ReopenSiteAttendance $action): RedirectResponse
    {
        Gate::authorize('reopen', $siteAttendanceRegister);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($siteAttendanceRegister, $actor, (string) $request->validated('reason'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance reopened for correction.']);

        return to_route('workforce.attendance.show', $siteAttendanceRegister);
    }
}
