<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkforceExceptionReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WorkforceExceptionController
{
    public function __invoke(Request $request, WorkforceExceptionReport $report): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can('workforce.reports.view'), 403);

        /** @var array{project_id?: string|null, site_id?: string|null, date_from?: string|null, date_to?: string|null} $validated */
        $validated = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'site_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $filters = [
            'project_id' => $validated['project_id'] ?? null,
            'site_id' => $validated['site_id'] ?? null,
            'date_from' => $validated['date_from'] ?? CarbonImmutable::now()->subDays(30)->toDateString(),
            'date_to' => $validated['date_to'] ?? CarbonImmutable::now()->toDateString(),
        ];

        return Inertia::render('operations/workforce/exceptions/index', [
            ...$report->handle($actor, $filters),
            'filters' => $filters,
        ]);
    }
}
