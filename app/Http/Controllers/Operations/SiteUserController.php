<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Actions\Operations\Sites\AssignSiteUsers;
use App\Http\Requests\Operations\Sites\AssignSiteUsersRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class SiteUserController
{
    public function store(AssignSiteUsersRequest $request, Site $site, AssignSiteUsers $action): RedirectResponse
    {
        Gate::authorize('update', $site);
        Gate::authorize('site-users.manage');

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        /** @var array{users?: list<array{user_id: string, role?: string|null, can_submit_dsr?: bool, can_review_dsr?: bool}>} $data */
        $data = $request->validated();
        $action->handle($site, $data['users'] ?? [], $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Site access updated.']);

        return to_route('sites.show', $site);
    }
}
