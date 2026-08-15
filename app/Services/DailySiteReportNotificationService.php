<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\User;

final readonly class DailySiteReportNotificationService
{
    public function __construct(
        private DailySiteReportRecipientResolver $recipients,
        private OperationalNotificationSender $notifications,
    ) {
        //
    }

    public function submitted(DailySiteReport $report): void
    {
        $this->notifications->send(
            $this->recipients->reviewers($report)->reject(fn (User $user): bool => $user->id === $report->submitted_by),
            $this->payload($report, 'dsr_submitted', 'info', 'Daily report awaiting review', sprintf('%s submitted %s.', $report->site?->name, $report->reference)),
        );
    }

    public function returned(DailySiteReport $report): void
    {
        $this->notifications->send(
            $this->participants($report),
            $this->payload($report, 'dsr_returned', 'warning', 'Daily report returned', sprintf('%s was returned: %s', $report->reference, $report->return_reason)),
        );
    }

    public function approved(DailySiteReport $report): void
    {
        $this->notifications->send(
            $this->participants($report),
            $this->payload($report, 'dsr_approved', 'success', 'Daily report approved', sprintf('%s has been approved.', $report->reference)),
        );
    }

    public function correctionRequested(DailySiteReportCorrection $correction): void
    {
        $correction->loadMissing('report');
        $report = $correction->report;

        if (! $report instanceof DailySiteReport) {
            return;
        }

        $this->notifications->send(
            $this->recipients->reviewers($report)->reject(fn (User $user): bool => $user->id === $correction->requested_by),
            $this->payload($report, 'dsr_correction', 'warning', 'Daily report correction requested', sprintf('A correction was requested for %s.', $report->reference)),
        );
    }

    public function correctionDecided(DailySiteReportCorrection $correction): void
    {
        $correction->loadMissing('report');
        $report = $correction->report;
        $requester = User::query()->whereKey($correction->requested_by)->where('is_active', true)->first();

        if (! $report instanceof DailySiteReport || ! $requester instanceof User) {
            return;
        }

        $this->notifications->send(
            collect([$requester]),
            $this->payload($report, 'dsr_correction', $correction->status === DailySiteReportCorrection::STATUS_APPROVED ? 'success' : 'warning', 'Daily report correction '.$correction->status, sprintf('Your correction for %s was %s.', $report->reference, $correction->status)),
        );
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function participants(DailySiteReport $report): \Illuminate\Support\Collection
    {
        $report->loadMissing(['submittedBy', 'site.manager']);

        return collect([$report->submittedBy, $report->site?->manager])
            ->filter(fn (mixed $user): bool => $user instanceof User && $user->is_active)
            ->unique('id')
            ->values();
    }

    /** @return array<string, mixed> */
    private function payload(DailySiteReport $report, string $category, string $severity, string $title, string $message): array
    {
        return [
            'tenant_id' => $report->tenant_id,
            'branch_id' => $report->branch_id,
            'project_id' => $report->project_id,
            'site_id' => $report->site_id,
            'daily_site_report_id' => $report->id,
            'category' => $category,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => '/daily-site-reports/'.$report->id,
        ];
    }
}
