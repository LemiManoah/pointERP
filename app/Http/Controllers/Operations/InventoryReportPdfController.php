<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\InventoryOperationsReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

final class InventoryReportPdfController
{
    public function __invoke(string $report, Request $request, InventoryOperationsReport $reports): Response
    {
        Gate::authorize('viewAny', InventoryStockMovement::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($actor->can($report === 'dsr-materials' ? 'inventory.dsr-reconciliation.export' : 'inventory.reports.export'), 403);

        /** @var array<string, string|null> $filters */
        $filters = $request->validate([
            'branch_id' => ['nullable', 'uuid'], 'store_id' => ['nullable', 'uuid'], 'project_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'], 'item_id' => ['nullable', 'uuid'], 'category_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $export = $reports->export($report, $actor, $filters);
        $filterSummary = collect($filters)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value, string $key): string => str($key)->replace('_', ' ')->title().' = '.$value)
            ->implode(' | ');

        return Pdf::loadView('reports.inventory', [
            'title' => str($export['filename'])->replace('-', ' ')->title()->toString(),
            'generatedAt' => now()->toDateTimeString(),
            'filterSummary' => $filterSummary,
            'headers' => $export['headers'],
            'rows' => $export['rows'],
        ])->setPaper('a4', 'landscape')->download($export['filename'].'-'.now()->format('Y-m-d-His').'.pdf');
    }
}
