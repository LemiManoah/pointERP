<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Projects\AssignProjectUsers;
use App\Http\Requests\Operations\Projects\AssignProjectUsersRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class ProjectUserController
{
    public function store(AssignProjectUsersRequest $request, Project $project, AssignProjectUsers $action): RedirectResponse
    {
        Gate::authorize('update', $project);
        Gate::authorize('project-users.manage');

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{users?: list<array{user_id: string, role?: string|null, can_manage?: bool}>} $data */
        $data = $request->validated();
        $action->handle($project, $data['users'] ?? [], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project access updated.']);

        return to_route('projects.show', $project);
    }
}
