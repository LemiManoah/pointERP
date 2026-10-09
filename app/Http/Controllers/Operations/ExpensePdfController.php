<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Expense;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ExpenseRegisterExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class ExpensePdfController
{
    public function __invoke(Request $request, ExpenseRegisterExport $export, AuditLogger $auditLogger): Response
    {
        Gate::authorize('viewAny', Expense::class);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('expenses.export') && $user->can('expenses.view-costs'), 403);
        $register = $export->for($user, $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:40'],
            'branch' => ['nullable', 'string', 'max:255'],
            'project' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
        ]));
        $auditLogger->record('expenses.export.pdf', $user, $user, properties: ['row_count' => count($register['rows'])]);

        return Pdf::loadView('reports.inventory', [
            'title' => 'Expense register',
            'generatedAt' => now()->toDateTimeString(),
            'filterSummary' => 'Accessible branches',
            'headers' => $register['headers'],
            'rows' => $register['rows'],
        ])->setPaper('a4', 'landscape')->download('expense-register-'.now()->format('Y-m-d-His').'.pdf');
    }
}
