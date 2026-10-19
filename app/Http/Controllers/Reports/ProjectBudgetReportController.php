<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Requests\Reports\ProjectBudgetReportRequest;
use App\Models\Branch;
use App\Models\Project;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\ProjectBudgetReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProjectBudgetReportController
{
    public function __invoke(ProjectBudgetReportRequest $request, ProjectBudgetReport $report, BranchContext $branches): Response
    {
        Gate::authorize('viewAny', Project::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $currentBranch = $branches->current($user);
        $selectedCurrency = $request->validated('currency');
        $currency = is_string($selectedCurrency)
            ? $selectedCurrency
            : ($currentBranch instanceof Branch ? $currentBranch->default_currency_code : $user->tenant->default_currency_code);
        $currencies = Project::query()->visibleTo($user)
            ->when($currentBranch instanceof Branch, fn (Builder $query): Builder => $query->where('branch_id', $currentBranch->id))
            ->distinct()->pluck('base_currency_code')->push($currency)->unique()->sort()->values();

        return Inertia::render('reports/project-budgets', [
            'report' => $report->for($user, $currency),
            'currencies' => $currencies,
        ]);
    }
}
