<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Estimates\ApproveProjectEstimate;
use App\Models\ProjectEstimate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ProjectEstimateApprovalController
{
    public function __invoke(Request $request, ProjectEstimate $projectEstimate, ApproveProjectEstimate $action): RedirectResponse
    {
        Gate::authorize('approve', $projectEstimate);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($projectEstimate, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estimate approved as the project baseline. Work items are ready for reporting.']);

        return to_route('projects.show', $projectEstimate->project_id);
    }
}
