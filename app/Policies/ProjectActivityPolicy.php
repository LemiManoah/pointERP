<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;

final class ProjectActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view')
            || $user->can('project-activities.manage')
            || $user->can('projects.view-all');
    }

    public function view(User $user, ProjectActivity $projectActivity): bool
    {
        return $user->can('project-activities.manage')
            || $user->can('projects.view-all')
            || $projectActivity->project->users()->whereKey($user->id)->exists()
            || $projectActivity->project->manager_id === $user->id;
    }

    public function create(User $user, ?Project $project = null): bool
    {
        if (! $user->can('project-activities.manage')) {
            return false;
        }

        if (! $project instanceof Project) {
            return true;
        }

        return $project->tenant_id === $user->tenant_id;
    }

    public function update(User $user, ProjectActivity $projectActivity): bool
    {
        return $user->can('project-activities.manage')
            && $projectActivity->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, ProjectActivity $projectActivity): bool
    {
        return $this->update($user, $projectActivity);
    }
}
