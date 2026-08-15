<?php

declare(strict_types=1);

namespace App\Actions\Operations\DailySiteReports;

use App\Models\DailySiteReportCorrection;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DailySiteReportNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RejectDailySiteReportCorrection
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DailySiteReportNotificationService $notificationService,
    ) {
        //
    }

    public function handle(DailySiteReportCorrection $correction, User $actor, string $reason): DailySiteReportCorrection
    {
        return DB::transaction(function () use ($actor, $correction, $reason): DailySiteReportCorrection {
            $correction->loadMissing('report');
            $report = $correction->report;

            if ($correction->status !== DailySiteReportCorrection::STATUS_SUBMITTED || $report === null) {
                throw ValidationException::withMessages([
                    'correction' => 'Only a pending correction can be rejected.',
                ]);
            }

            $correction->forceFill([
                'approved_by' => $actor->id,
                'status' => DailySiteReportCorrection::STATUS_REJECTED,
                'approved_at' => null,
                'rejected_at' => now(),
            ])->save();

            $this->auditLogger->record(
                'operations.daily_site_report.correction_rejected',
                $report,
                $actor,
                $correction->old_values ?? [],
                $correction->new_values ?? [],
                $reason,
            );
            DB::afterCommit(fn () => $this->notificationService->correctionDecided($correction));

            return $correction;
        });
    }
}
