<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;
use Illuminate\Database\Eloquent\Builder;

final class ProjectPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('projects.view')) {
            return true;
        }

        if ($user->can('projects.create')) {
            return true;
        }

        if ($user->can('projects.update')) {
            return true;
        }

        return $user->can('projects.view-all');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->belongsToSameTenant($user, $project->tenant_id)
            && $this->canAccessBranch($user, $project->branch_id)
            && $this->viewAny($user)
            && (
                $user->can('projects.view-all')
                || $user->can('branches.view-all')
                || $project->manager_id === $user->id
                || $project->users()->whereKey($user->id)->exists()
                || $project->sites()->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))->exists()
            );
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        if (! $this->belongsToSameTenant($user, $project->tenant_id) || ! $this->canAccessBranch($user, $project->branch_id)) {
            return false;
        }

        if ($user->can('projects.view-all') || $user->can('projects.update')) {
            return true;
        }

        return $project->manager_id === $user->id
            || $project->users()->whereKey($user->id)->wherePivot('can_manage', true)->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project)
            && $user->can('projects.archive');
    }
}
