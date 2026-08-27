<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DailySiteReportMaterialLine;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class DailySiteReportMaterialLinePolicy
{
    public function view(User $user, DailySiteReportMaterialLine $line): bool
    {
        return $line->report->isApproved()
            && $user->can('inventory.dsr-reconciliation.view')
            && Gate::forUser($user)->allows('view', $line->report);
    }

    public function manage(User $user, DailySiteReportMaterialLine $line): bool
    {
        return $this->view($user, $line) && $user->can('inventory.dsr-reconciliation.manage');
    }

    public function directIssue(User $user, DailySiteReportMaterialLine $line): bool
    {
        return $this->view($user, $line) && $user->can('inventory.dsr-reconciliation.direct-issue');
    }

    public function markExternal(User $user, DailySiteReportMaterialLine $line): bool
    {
        return $this->view($user, $line) && $user->can('inventory.dsr-reconciliation.mark-external');
    }
}
