<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\ProjectActivities\SaveProjectActivity;
use App\Http\Requests\Operations\ProjectActivities\StoreProjectActivityRequest;
use App\Http\Requests\Operations\ProjectActivities\UpdateProjectActivityRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ProjectActivityController
{
    public function store(StoreProjectActivityRequest $request, SaveProjectActivity $action): RedirectResponse
    {
        /** @var array{project_id: string, site_id?: string|null, code?: string|null, boq_item_number?: string|null, name: string, unit?: string|null, planned_quantity?: string|null, approved_quantity?: string|null, rate_amount?: string|null, currency_code?: string|null, status: string, sort_order?: int|string|null} $data */
        $data = $request->validated();
        $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

        Gate::authorize('create', [ProjectActivity::class, $project]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Activity saved.']);

        return to_route('projects.show', $project);
    }

    public function update(UpdateProjectActivityRequest $request, ProjectActivity $projectActivity, SaveProjectActivity $action): RedirectResponse
    {
        Gate::authorize('update', $projectActivity);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{project_id: string, site_id?: string|null, code?: string|null, boq_item_number?: string|null, name: string, unit?: string|null, planned_quantity?: string|null, approved_quantity?: string|null, rate_amount?: string|null, currency_code?: string|null, status: string, sort_order?: int|string|null} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $projectActivity);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Activity updated.']);

        return to_route('projects.show', $projectActivity->project_id);
    }

    public function destroy(ProjectActivity $projectActivity, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $projectActivity);

        $oldStatus = $projectActivity->status;
        $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';

        $projectActivity->update(['status' => $newStatus]);
        $auditLogger->record(
            event: 'operations.project_activity.status_changed',
            subject: $projectActivity,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Activity status changed.']);

        return to_route('projects.show', $projectActivity->project_id);
    }
}
