<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;

final class DailySiteReportRecipientResolver
{
    /** @return Collection<int, User> */
    public function submitters(Site $site): Collection
    {
        $users = $site->users()
            ->where('users.is_active', true)
            ->wherePivot('can_submit_dsr', true)
            ->get();

        if ($site->manager instanceof User && $site->manager->is_active) {
            $users->push($site->manager);
        }

        return $this->unique($users, $site->tenant_id);
    }

    /** @return Collection<int, User> */
    public function reviewers(DailySiteReport $report): Collection
    {
        $report->loadMissing(['site.manager', 'site.users', 'project.manager', 'project.users']);
        $users = $report->site->users()
            ->where('users.is_active', true)
            ->wherePivot('can_review_dsr', true)
            ->get();

        if ($report->project?->manager instanceof User) {
            $users->push($report->project->manager);
        }

        foreach ($report->project?->users ?? [] as $user) {
            if ($user->pivot?->can_manage) {
                $users->push($user);
            }
        }

        return $this->unique($users, $report->tenant_id);
    }

    /** @return Collection<int, User> */
    public function escalationRecipients(Site $site): Collection
    {
        $site->loadMissing(['project.manager', 'project.users']);
        $users = collect();

        if ($site->project?->manager instanceof User) {
            $users->push($site->project->manager);
        }

        foreach ($site->project?->users ?? [] as $user) {
            if ($user->pivot?->can_manage) {
                $users->push($user);
            }
        }

        User::query()
            ->where('tenant_id', $site->tenant_id)
            ->where('is_active', true)
            ->where('is_director', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can('operations-dashboard.view'))
            ->each(fn (User $user) => $users->push($user));

        return $this->unique($users, $site->tenant_id);
    }

    /** @param Collection<int, User> $users
     *  @return Collection<int, User>
     */
    private function unique(Collection $users, string $tenantId): Collection
    {
        return $users
            ->filter(fn (User $user): bool => $user->tenant_id === $tenantId && $user->is_active)
            ->unique('id')
            ->values();
    }
}
