<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class SitePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('sites.view')
            || $user->can('sites.create')
            || $user->can('sites.update')
            || $user->can('sites.view-all');
    }

    public function view(User $user, Site $site): bool
    {
        return $this->belongsToSameTenant($user, $site->tenant_id)
            && $this->canAccessBranch($user, $site->branch_id)
            && $this->viewAny($user)
            && (
                $user->can('sites.view-all')
                || $user->can('projects.view-all')
                || $user->can('branches.view-all')
                || $site->manager_id === $user->id
                || $site->users()->whereKey($user->id)->exists()
                || $site->project->users()->whereKey($user->id)->exists()
            );
    }

    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->can('sites.create')) {
            return false;
        }

        if (! $project instanceof Project) {
            return true;
        }

        return $this->belongsToSameTenant($user, $project->tenant_id)
            && $this->canAccessBranch($user, $project->branch_id);
    }

    public function update(User $user, Site $site): bool
    {
        if (! $this->belongsToSameTenant($user, $site->tenant_id) || ! $this->canAccessBranch($user, $site->branch_id)) {
            return false;
        }

        if ($user->can('sites.view-all') || $user->can('sites.update')) {
            return true;
        }

        return $site->manager_id === $user->id
            || $site->project->manager_id === $user->id
            || $site->project->users()->whereKey($user->id)->wherePivot('can_manage', true)->exists();
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->update($user, $site)
            && $user->can('sites.archive');
    }
}
