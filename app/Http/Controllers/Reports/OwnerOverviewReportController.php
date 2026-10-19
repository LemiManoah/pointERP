<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Actions\BuildOwnerOverview;
use App\Http\Requests\DashboardRequest;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

final class OwnerOverviewReportController
{
    public function __invoke(DashboardRequest $request, BuildOwnerOverview $overview): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && ReportIndexController::canViewOwnerOverview($user), 403);

        /** @var array{period?: string, from?: string|null, to?: string|null, currency?: string|null} $filters */
        $filters = $request->validated();

        return Inertia::render('reports/owner-overview', ['report' => $overview->handle($user, $filters)]);
    }
}
