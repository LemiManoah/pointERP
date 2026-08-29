<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class ProjectEstimatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estimates.view');
    }

    public function view(User $user, ProjectEstimate $estimate): bool
    {
        return $user->can('estimates.view')
            && Gate::forUser($user)->allows('view', $estimate->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('estimates.create')
            && $user->can('estimates.view-costs')
            && Gate::forUser($user)->allows('view', $project);
    }

    public function update(User $user, ProjectEstimate $estimate): bool
    {
        return $estimate->isDraft()
            && $user->can('estimates.update')
            && $user->can('estimates.view-costs')
            && Gate::forUser($user)->allows('view', $estimate->project);
    }

    public function approve(User $user, ProjectEstimate $estimate): bool
    {
        return $estimate->isDraft()
            && $user->can('estimates.approve')
            && $user->can('estimates.view-costs')
            && Gate::forUser($user)->allows('view', $estimate->project);
    }

    public function delete(User $user, ProjectEstimate $estimate): bool
    {
        return $this->update($user, $estimate);
    }

    public function viewCosts(User $user, ProjectEstimate $estimate): bool
    {
        return $this->view($user, $estimate)
            && $user->can('estimates.view-costs');
    }
}
