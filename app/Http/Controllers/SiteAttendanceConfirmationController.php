<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Workforce\ConfirmSiteAttendance;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class SiteAttendanceConfirmationController
{
    public function __invoke(Request $request, SiteAttendanceRegister $siteAttendanceRegister, ConfirmSiteAttendance $action): RedirectResponse
    {
        Gate::authorize('confirm', $siteAttendanceRegister);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($siteAttendanceRegister, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance confirmed.']);

        return to_route('workforce.attendance.show', $siteAttendanceRegister);
    }
}
