<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\Expense;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ExpenseRegisterExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExpenseExportController
{
    public function __invoke(Request $request, ExpenseRegisterExport $export, AuditLogger $auditLogger): StreamedResponse
    {
        Gate::authorize('viewAny', Expense::class);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('expenses.export') && $user->can('expenses.view-costs'), 403);
        $register = $export->for($user);
        $auditLogger->record('expenses.export.csv', $user, $user, properties: ['row_count' => count($register['rows'])]);

        return response()->streamDownload(function () use ($register): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Generated at', now()->toDateTimeString()], escape: '\\');
            fputcsv($handle, [], escape: '\\');
            fputcsv($handle, $register['headers'], escape: '\\');
            foreach ($register['rows'] as $row) {
                fputcsv($handle, $row, escape: '\\');
            }

            fclose($handle);
        }, 'expense-register-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
