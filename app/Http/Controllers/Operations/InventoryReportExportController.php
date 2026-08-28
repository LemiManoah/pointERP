<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\InventoryOperationsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InventoryReportExportController
{
    public function __invoke(string $report, Request $request, InventoryOperationsReport $reports): StreamedResponse
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($actor->can($report === 'dsr-materials' ? 'inventory.dsr-reconciliation.export' : 'inventory.reports.export'), 403);

        $filters = $request->validate([
            'branch_id' => ['nullable', 'uuid'], 'store_id' => ['nullable', 'uuid'], 'project_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'], 'item_id' => ['nullable', 'uuid'], 'category_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $export = $reports->export($report, $actor, $filters);

        return response()->streamDownload(function () use ($export, $filters): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Generated at', now()->toDateTimeString()], escape: '\\');
            fputcsv($handle, ['Filters', json_encode(array_filter($filters, fn (mixed $value): bool => is_string($value) && $value !== ''), JSON_THROW_ON_ERROR)], escape: '\\');
            fputcsv($handle, [], escape: '\\');
            fputcsv($handle, $export['headers'], escape: '\\');
            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row, escape: '\\');
            }

            fclose($handle);
        }, $export['filename'].'-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
