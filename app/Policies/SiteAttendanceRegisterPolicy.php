<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AttendanceRegisterStatus;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteAttendanceRegister;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class SiteAttendanceRegisterPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('workforce.view')) {
            return true;
        }

        if ($user->can('workforce.attendance.record')) {
            return true;
        }

        if ($user->can('workforce.attendance.confirm')) {
            return true;
        }

        return $user->can('workforce.attendance.reopen');
    }

    public function view(User $user, SiteAttendanceRegister $register): bool
    {
        return $this->belongsToSameTenant($user, $register->tenant_id)
            && $this->canAccessBranch($user, $register->branch_id)
            && $this->viewAny($user)
            && $this->canAccessSite($user, $register->site);
    }

    public function create(User $user, ?Site $site = null): bool
    {
        if (! $user->can('workforce.attendance.record')) {
            return false;
        }

        return ! $site instanceof Site
            || ($this->belongsToSameTenant($user, $site->tenant_id)
                && $this->canAccessBranch($user, $site->branch_id)
                && $this->canAccessSite($user, $site));
    }

    public function update(User $user, SiteAttendanceRegister $register): bool
    {
        return $register->status === AttendanceRegisterStatus::Draft
            && $user->can('workforce.attendance.record')
            && $this->view($user, $register);
    }

    public function confirm(User $user, SiteAttendanceRegister $register): bool
    {
        return $register->status === AttendanceRegisterStatus::Draft
            && $user->can('workforce.attendance.confirm')
            && $this->view($user, $register);
    }

    public function reopen(User $user, SiteAttendanceRegister $register): bool
    {
        return $register->status === AttendanceRegisterStatus::Confirmed
            && $user->can('workforce.attendance.reopen')
            && $this->view($user, $register);
    }

    private function canAccessSite(User $user, Site $site): bool
    {
        if ($user->can('sites.view-all') || $user->can('projects.view-all') || $user->can('branches.view-all')) {
            return true;
        }

        if ($site->manager_id === $user->id || $site->users()->whereKey($user->id)->exists()) {
            return true;
        }

        $project = $site->project;

        return $project instanceof Project
            && ($project->manager_id === $user->id || $project->users()->whereKey($user->id)->exists());
    }
}
