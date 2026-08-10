<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Sites\SaveSite;
use App\Http\Requests\Operations\Sites\StoreSiteRequest;
use App\Http\Requests\Operations\Sites\UpdateSiteRequest;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SiteController
{
    public function show(Site $site): Response
    {
        Gate::authorize('view', $site);

        $site->load(['branch', 'project', 'manager', 'users', 'activities']);

        return Inertia::render('operations/sites/show', [
            'site' => [
                'id' => $site->id,
                'project_id' => $site->project_id,
                'project_name' => $site->project->name,
                'branch_name' => $site->branch->name,
                'reference' => $site->reference,
                'name' => $site->name,
                'location_name' => $site->location_name,
                'latitude' => $site->latitude,
                'longitude' => $site->longitude,
                'manager_id' => $site->manager_id,
                'manager_name' => $site->manager?->name,
                'reporting_deadline' => $site->reporting_deadline,
                'status' => $site->status,
            ],
            'assignedUsers' => $site->users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'can_submit_dsr' => (bool) $user->pivot->can_submit_dsr,
                'can_review_dsr' => (bool) $user->pivot->can_review_dsr,
            ]),
            'users' => User::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('is_active', true)
                ->whereHas('branches', fn ($query) => $query->whereKey($site->branch_id))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => sprintf('%s (%s)', $user->name, $user->email), 'email' => $user->email]),
        ]);
    }

    public function store(StoreSiteRequest $request, SaveSite $action): RedirectResponse
    {
        /** @var array{project_id: string, reference: string, name: string, location_name?: string|null, latitude?: string|null, longitude?: string|null, manager_id?: string|null, reporting_deadline?: string|null, status: string} $data */
        $data = $request->validated();
        $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

        Gate::authorize('create', [Site::class, $project]);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $site = $action->handle($data, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site saved.']);

        return to_route('projects.show', $site->project_id);
    }

    public function update(UpdateSiteRequest $request, Site $site, SaveSite $action): RedirectResponse
    {
        Gate::authorize('update', $site);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{project_id: string, reference: string, name: string, location_name?: string|null, latitude?: string|null, longitude?: string|null, manager_id?: string|null, reporting_deadline?: string|null, status: string} $data */
        $data = $request->validated();
        $action->handle($data, $actor, $site);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site updated.']);

        return to_route('projects.show', $site->project_id);
    }

    public function destroy(Site $site, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $site);

        $oldStatus = $site->status;
        $newStatus = $oldStatus === 'archived' ? 'active' : 'archived';

        $site->update(['status' => $newStatus]);
        $auditLogger->record(
            event: 'operations.site.status_changed',
            subject: $site,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site archive status changed.']);

        return to_route('projects.show', $site->project_id);
    }
}
