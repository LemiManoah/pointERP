<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItemTemplate;
use App\Policies\Concerns\ChecksTenantAccess;

final class WorkItemTemplatePolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('work-item-templates.view') || $user->can('work-item-templates.manage');
    }

    public function view(User $user, WorkItemTemplate $template): bool
    {
        return $this->belongsToSameTenant($user, $template->tenant_id) && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('work-item-templates.manage');
    }

    public function update(User $user, WorkItemTemplate $template): bool
    {
        return $this->belongsToSameTenant($user, $template->tenant_id) && $this->create($user);
    }

    public function delete(User $user, WorkItemTemplate $template): bool
    {
        return $this->update($user, $template);
    }
}
