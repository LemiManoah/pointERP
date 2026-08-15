<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;
use App\Models\DocumentLink;
use App\Models\ExpectedDailySiteReport;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class DsrExceptionReport
{
    public function __construct(private BranchContext $branchContext)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(User $user, array $filters): Collection
    {
        $from = is_string($filters['from'] ?? null) ? $filters['from'] : now()->subDays(30)->toDateString();
        $to = is_string($filters['to'] ?? null) ? $filters['to'] : now()->toDateString();

        $rows = ExpectedDailySiteReport::query()
            ->with(['site.project', 'site.manager', 'report'])
            ->whereIn('branch_id', $this->branchContext->accessibleBranchIds($user))
            ->whereBetween('report_date', [$from, $to])
            ->when(is_string($filters['project_id'] ?? null) ? $filters['project_id'] : null, fn ($query, string $id) => $query->where('project_id', $id))
            ->when(is_string($filters['site_id'] ?? null) ? $filters['site_id'] : null, fn ($query, string $id) => $query->where('site_id', $id))
            ->when(is_string($filters['status'] ?? null) ? $filters['status'] : null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('report_date')
            ->get()
            ->filter(fn (ExpectedDailySiteReport $expected): bool => $expected->site instanceof Site
                && Gate::forUser($user)->allows('view', $expected->site))
            ->map(function (ExpectedDailySiteReport $expected): array {
                $report = $expected->report;
                $missingEvidence = $report instanceof DailySiteReport
                    && $report->workLines()->exists()
                    && ! DocumentLink::query()
                        ->where('linkable_type', DailySiteReport::class)
                        ->where('linkable_id', $report->id)
                        ->exists();

                return [
                    'id' => $expected->id,
                    'branch_id' => $expected->branch_id,
                    'project_id' => $expected->project_id,
                    'project_name' => $expected->site?->project?->name,
                    'site_id' => $expected->site_id,
                    'site_name' => $expected->site?->name,
                    'site_manager' => $expected->site?->manager?->name,
                    'report_date' => $expected->report_date->toDateString(),
                    'deadline_at' => $expected->deadline_at?->toDateTimeString(),
                    'status' => $expected->status,
                    'report_id' => $report?->id,
                    'report_reference' => $report?->reference,
                    'report_status' => $report?->status,
                    'submitted_at' => $expected->submitted_at?->toDateTimeString(),
                    'notified_at' => $expected->notified_at?->toDateTimeString(),
                    'escalated_at' => $expected->escalated_at?->toDateTimeString(),
                    'missing_evidence' => $missingEvidence,
                ];
            })
            ->values();

        /** @var Collection<int, array<string, mixed>> $rows */
        return $rows;
    }

    /** @param Collection<int, array<string, mixed>> $rows
     * @return array<string, int|float>
     */
    public function summary(Collection $rows): array
    {
        $expected = $rows->count();
        $onTime = $rows->where('status', ExpectedDailySiteReport::STATUS_SUBMITTED)->count();
        $late = $rows->where('status', ExpectedDailySiteReport::STATUS_LATE)->count();

        return [
            'expected' => $expected,
            'on_time' => $onTime,
            'late' => $late,
            'missing' => $rows->where('status', ExpectedDailySiteReport::STATUS_MISSING)->count(),
            'excused' => $rows->where('status', ExpectedDailySiteReport::STATUS_EXCUSED)->count(),
            'returned' => $rows->where('report_status', DailySiteReport::STATUS_RETURNED)->count(),
            'pending' => $rows->whereIn('report_status', [DailySiteReport::STATUS_SUBMITTED, DailySiteReport::STATUS_REVIEWED])->count(),
            'missing_evidence' => $rows->where('missing_evidence', true)->count(),
            'compliance_percent' => $expected > 0 ? round((($onTime + $late) / $expected) * 100, 1) : 0.0,
        ];
    }
}
