<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;
use Illuminate\Database\Eloquent\Builder;

final class ProjectActivityPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('projects.view')) {
            return true;
        }

        if ($user->can('project-activities.manage')) {
            return true;
        }

        return $user->can('projects.view-all');
    }

    public function view(User $user, ProjectActivity $projectActivity): bool
    {
        if ($projectActivity->tenant_id !== $user->tenant_id || ! $this->canAccessBranch($user, $projectActivity->branch_id)) {
            return false;
        }

        if ($user->can('project-activities.manage')) {
            return true;
        }

        if ($user->can('projects.view-all')) {
            return true;
        }

        if ($projectActivity->project->users()->whereKey($user->id)->exists()) {
            return true;
        }

        if ($projectActivity->project->manager_id === $user->id) {
            return true;
        }

        if ($projectActivity->site?->users()->whereKey($user->id)->exists()) {
            return true;
        }

        return (bool) $projectActivity->project->sites()->whereHas('users', fn (Builder $query) => $query->whereKey($user->id))->exists();
    }

    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->can('project-activities.manage')) {
            return false;
        }

        if (! $project instanceof Project) {
            return true;
        }

        return $project->tenant_id === $user->tenant_id
            && $this->canAccessBranch($user, $project->branch_id);
    }

    public function update(User $user, ProjectActivity $projectActivity): bool
    {
        return $user->can('project-activities.manage')
            && $projectActivity->tenant_id === $user->tenant_id
            && $this->canAccessBranch($user, $projectActivity->branch_id);
    }

    public function delete(User $user, ProjectActivity $projectActivity): bool
    {
        return $this->update($user, $projectActivity);
    }
}
