<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReportingCalendar;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class ReportingCalendarPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('reporting-calendars.view') || $user->can('reporting-calendars.manage');
    }

    public function view(User $user, ReportingCalendar $calendar): bool
    {
        return $this->viewAny($user)
            && $this->belongsToSameTenant($user, $calendar->tenant_id)
            && ($calendar->branch_id === null || $this->canAccessBranch($user, $calendar->branch_id));
    }

    public function create(User $user): bool
    {
        return $user->can('reporting-calendars.manage');
    }

    public function update(User $user, ReportingCalendar $calendar): bool
    {
        return $user->can('reporting-calendars.manage') && $this->view($user, $calendar);
    }

    public function delete(User $user, ReportingCalendar $calendar): bool
    {
        return $this->update($user, $calendar) && $calendar->is_active;
    }
}
