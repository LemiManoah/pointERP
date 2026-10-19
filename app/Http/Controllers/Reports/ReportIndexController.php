<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReportIndexController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $reports = [];
        if (self::canViewOwnerOverview($user)) {
            $reports[] = ['title' => 'Owner overview', 'description' => 'Review approved expenses, recorded payments, active project budgets, and current exceptions.', 'href' => route('reports.owner-overview')];
        }

        if ($user->can('projects.view-all')) {
            $reports[] = ['title' => 'Project budgets', 'description' => 'Compare project budgets with recorded spend and remaining budget.', 'href' => route('reports.project-budgets')];
        }

        if ($user->can('expenses.view') && $user->can('expenses.view-costs')) {
            $reports[] = ['title' => 'Expenses', 'description' => 'Filter the expense register and export its report.', 'href' => route('expenses.index', ['tab' => 'reports'])];
        }

        if ($user->can('inventory.stock.view') && $user->can('inventory.reports.export')) {
            $reports[] = ['title' => 'Inventory', 'description' => 'Review stock and export inventory reports.', 'href' => route('inventory.dashboard')];
        }

        if ($user->can('equipment.view')) {
            $reports[] = ['title' => 'Equipment', 'description' => 'Review equipment activity, fuel, and maintenance.', 'href' => route('equipment.index')];
        }

        if ($user->can('operations-dashboard.view')) {
            $reports[] = ['title' => 'Operations', 'description' => 'Review site-report activity and exceptions.', 'href' => route('operations-dashboard.index')];
        }

        abort_if($reports === [], 403);

        return Inertia::render('reports/index', ['reports' => $reports]);
    }

    public static function canViewOwnerOverview(User $user): bool
    {
        foreach (['projects.view', 'projects.view-all', 'dashboards.projects.view', 'pos.view', 'pos.view-all-sales', 'pos.view-payments', 'expenses.view', 'expenses.view-costs', 'expense-payments.view'] as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
