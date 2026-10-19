<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\DailySiteReport;
use App\Models\ExpenseLine;
use App\Models\Project;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

final readonly class ProjectBudgetReport
{
    public function __construct(private BranchContext $branches) {}

    /** @return array{currency: string, total_budget: string, total_spent: string|null, total_remaining: string|null, project_count: int, budgeted_count: int, unbudgeted_count: int, uncomparable_count: int, rows: list<array{id: string, reference: string, name: string, branch: string, status: string, budget: string|null, spent: string|null, remaining: string|null}>} */
    public function for(User $user, string $currencyCode): array
    {
        $currentBranch = $this->branches->current($user);
        $projects = Project::query()
            ->visibleTo($user)
            ->when($currentBranch instanceof Branch, fn (Builder $query): Builder => $query->where('branch_id', $currentBranch->id))
            ->where('base_currency_code', $currencyCode)
            ->with('branch')
            ->orderBy('name')
            ->get()
            ->filter(fn (Project $project): bool => Gate::forUser($user)->allows('view', $project));
        $projectIds = $projects->modelKeys();
        $reportCosts = DailySiteReport::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', DailySiteReport::STATUS_APPROVED)
            ->selectRaw('project_id, SUM(input_cost) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');
        $expenseCosts = ExpenseLine::query()
            ->whereIn('project_id', $projectIds)
            ->whereHas('expense', fn (Builder $query): Builder => $query->where('status', 'approved'))
            ->selectRaw('project_id, SUM(base_currency_amount) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $totalBudget = BigDecimal::zero();
        $totalSpent = BigDecimal::zero();
        $unbudgeted = 0;
        $uncomparable = 0;
        $rows = [];
        foreach ($projects as $project) {
            $budget = $project->budget_amount === null ? null : BigDecimal::of($project->budget_amount);
            $comparable = $project->branch->default_currency_code === $project->base_currency_code;
            $spent = $comparable
                ? BigDecimal::of((string) ($reportCosts->get($project->id) ?? '0'))->plus((string) ($expenseCosts->get($project->id) ?? '0'))
                : null;
            if ($budget === null) {
                $unbudgeted++;
            } else {
                $totalBudget = $totalBudget->plus($budget);
                if ($spent instanceof BigDecimal) {
                    $totalSpent = $totalSpent->plus($spent);
                } else {
                    $uncomparable++;
                }
            }

            $rows[] = [
                'id' => $project->id,
                'reference' => $project->reference,
                'name' => $project->name,
                'branch' => $project->branch->name,
                'status' => $project->status,
                'budget' => $budget === null ? null : (string) $budget->toScale(4),
                'spent' => $spent === null ? null : (string) $spent->toScale(4),
                'remaining' => $budget !== null && $spent !== null ? (string) $budget->minus($spent)->toScale(4) : null,
            ];
        }

        return [
            'currency' => $currencyCode,
            'total_budget' => (string) $totalBudget->toScale(4),
            'total_spent' => $uncomparable === 0 ? (string) $totalSpent->toScale(4) : null,
            'total_remaining' => $uncomparable === 0 ? (string) $totalBudget->minus($totalSpent)->toScale(4) : null,
            'project_count' => $projects->count(),
            'budgeted_count' => $projects->count() - $unbudgeted,
            'unbudgeted_count' => $unbudgeted,
            'uncomparable_count' => $uncomparable,
            'rows' => $rows,
        ];
    }
}
