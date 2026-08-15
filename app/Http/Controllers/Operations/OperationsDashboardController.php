<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\DsrExceptionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OperationsDashboardController
{
    public function __invoke(Request $request, DsrExceptionReport $report, BranchContext $branchContext): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('operations-dashboard.view'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'project_id' => ['nullable', 'uuid'],
            'site_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string'],
        ]);
        $rows = $report->rows($user, $filters);
        $branchIds = $branchContext->accessibleBranchIds($user);

        return Inertia::render('operations/dashboard/index', [
            'rows' => $rows,
            'summary' => $report->summary($rows),
            'filters' => [
                'from' => $filters['from'] ?? now()->subDays(30)->toDateString(),
                'to' => $filters['to'] ?? now()->toDateString(),
                'project_id' => $filters['project_id'] ?? '',
                'site_id' => $filters['site_id'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'projects' => Project::query()
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project))
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])
                ->values(),
            'sites' => Site::query()
                ->with('project')
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->filter(fn (Site $site): bool => Gate::forUser($user)->allows('view', $site))
                ->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'project_id' => $site->project_id,
                ])
                ->values(),
            'generatedAt' => now()->toDateTimeString(),
            'canExport' => $user->can('operations-dashboard.export'),
            'canExcuse' => $user->can('expected-daily-reports.excuse'),
        ]);
    }
}
