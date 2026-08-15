<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class DailySiteReportPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('daily-site-reports.view')) {
            return true;
        }

        if ($user->can('daily-site-reports.create')) {
            return true;
        }

        if ($user->can('daily-site-reports.review')) {
            return true;
        }

        return $user->can('daily-site-reports.approve');
    }

    public function view(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $this->belongsToSameTenant($user, $dailySiteReport->tenant_id)
            && $this->canAccessBranch($user, $dailySiteReport->branch_id)
            && $this->viewAny($user)
            && $dailySiteReport->site instanceof Site
            && $this->canAccessSite($user, $dailySiteReport->site);
    }

    public function create(User $user, ?Site $site = null): bool
    {
        if (! $user->can('daily-site-reports.create')) {
            return false;
        }

        if (! $site instanceof Site) {
            return true;
        }

        return $this->belongsToSameTenant($user, $site->tenant_id)
            && $this->canAccessBranch($user, $site->branch_id)
            && $this->canAccessSite($user, $site);
    }

    public function update(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->isEditable()
            && $user->can('daily-site-reports.update')
            && $this->view($user, $dailySiteReport)
            && $dailySiteReport->site instanceof Site
            && $dailySiteReport->project instanceof Project
            && (
                $dailySiteReport->created_by === $user->id
                || $dailySiteReport->submitted_by === $user->id
                || $dailySiteReport->site->users()->whereKey($user->id)->wherePivot('can_submit_dsr', true)->exists()
                || $dailySiteReport->project->manager_id === $user->id
                || $dailySiteReport->project->users()->whereKey($user->id)->wherePivot('can_manage', true)->exists()
            );
    }

    public function submit(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->isEditable()
            && $user->can('daily-site-reports.submit')
            && $this->view($user, $dailySiteReport)
            && $dailySiteReport->site instanceof Site
            && $dailySiteReport->site->users()->whereKey($user->id)->wherePivot('can_submit_dsr', true)->exists();
    }

    public function review(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->status === DailySiteReport::STATUS_SUBMITTED
            && $user->can('daily-site-reports.review')
            && $this->view($user, $dailySiteReport)
            && $dailySiteReport->site instanceof Site
            && $dailySiteReport->project instanceof Project
            && (
                $dailySiteReport->site->users()->whereKey($user->id)->wherePivot('can_review_dsr', true)->exists()
                || $dailySiteReport->project->manager_id === $user->id
                || $dailySiteReport->project->users()->whereKey($user->id)->wherePivot('can_manage', true)->exists()
                || $user->can('projects.view-all')
            );
    }

    public function approve(User $user, DailySiteReport $dailySiteReport): bool
    {
        return in_array($dailySiteReport->status, [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED], true)
            && $user->can('daily-site-reports.approve')
            && $this->view($user, $dailySiteReport)
            && (
                $dailySiteReport->submitted_by !== $user->id
                || $user->can('daily-site-reports.override-self-approval')
            )
            && $dailySiteReport->project instanceof Project
            && (
                $dailySiteReport->project->manager_id === $user->id
                || $dailySiteReport->project->users()->whereKey($user->id)->wherePivot('can_manage', true)->exists()
                || $user->can('projects.view-all')
            );
    }

    public function return(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->isSubmitted()
            && $user->can('daily-site-reports.return')
            && $this->review($user, $dailySiteReport);
    }

    public function correct(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->isApproved()
            && $user->can('daily-site-reports.correct')
            && $this->view($user, $dailySiteReport);
    }

    public function approveCorrection(User $user, DailySiteReport $dailySiteReport, DailySiteReportCorrection $correction): bool
    {
        return $correction->daily_site_report_id === $dailySiteReport->id
            && $correction->status === DailySiteReportCorrection::STATUS_SUBMITTED
            && $correction->requested_by !== $user->id
            && $user->can('daily-site-reports.approve')
            && $this->view($user, $dailySiteReport);
    }

    public function rejectCorrection(User $user, DailySiteReport $dailySiteReport, DailySiteReportCorrection $correction): bool
    {
        return $this->approveCorrection($user, $dailySiteReport, $correction);
    }

    public function archive(User $user, DailySiteReport $dailySiteReport): bool
    {
        return $dailySiteReport->status !== DailySiteReport::STATUS_ARCHIVED
            && $user->can('daily-site-reports.archive')
            && $this->view($user, $dailySiteReport);
    }

    private function canAccessSite(User $user, Site $site): bool
    {
        if ($user->can('sites.view-all')) {
            return true;
        }

        if ($user->can('projects.view-all')) {
            return true;
        }

        if ($user->can('branches.view-all')) {
            return true;
        }

        if ($site->manager_id === $user->id) {
            return true;
        }

        if ($site->users()->whereKey($user->id)->exists()) {
            return true;
        }

        if ($site->project->manager_id === $user->id) {
            return true;
        }

        return (bool) $site->project->users()->whereKey($user->id)->exists();
    }
}
