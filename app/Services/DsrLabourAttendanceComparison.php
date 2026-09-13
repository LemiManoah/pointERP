<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceRegisterStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DsrLabourAttendanceStatus;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportLabourLine;
use App\Models\SiteAttendanceRegister;

/**
 * Compares confirmed muster-roll person-hours with DSR labour by source,
 * workforce trade and subcontractor.
 */
final class DsrLabourAttendanceComparison
{
    /**
     * @return array{
     *   status: string,
     *   status_label: string,
     *   attendance_available: bool,
     *   register_count: int,
     *   registers: list<array{id: string, shift: string, confirmed_at: string|null}>,
     *   attended_hours: float,
     *   reported_hours: float,
     *   variance_hours: float,
     *   tolerance_hours: float,
     *   groups: list<array{
     *     key: string,
     *     labour_source: string,
     *     trade_id: string|null,
     *     trade_name: string,
     *     subcontractor_id: string|null,
     *     subcontractor_name: string|null,
     *     attended_hours: float,
     *     reported_hours: float,
     *     variance_hours: float,
     *     status: string,
     *     status_label: string
     *   }>
     * }
     */
    public function compare(DailySiteReport $report): array
    {
        $tolerance = (float) config('workforce.labour_variance_tolerance_hours', 0.25);
        $registers = SiteAttendanceRegister::query()
            ->with(['records.trade'])
            ->where('tenant_id', $report->tenant_id)
            ->where('site_id', $report->site_id)
            ->whereDate('attendance_date', $report->report_date->toDateString())
            ->where('status', AttendanceRegisterStatus::Confirmed->value)
            ->get();

        /** @var array<string, array{
         *   key: string,
         *   labour_source: string,
         *   trade_id: string|null,
         *   trade_name: string,
         *   subcontractor_id: string|null,
         *   subcontractor_name: string|null,
         *   attended_hours: float,
         *   reported_hours: float
         * }> $groups
         */
        $groups = [];

        foreach ($registers as $register) {
            foreach ($register->records as $record) {
                if ($record->attendance_status !== AttendanceStatus::Present) {
                    continue;
                }

                $key = $this->key(
                    $record->labour_source->value,
                    $record->workforce_trade_id,
                    $record->subcontractor_id,
                    $record->trade->name,
                );
                $groups[$key] ??= $this->group(
                    $key,
                    $record->labour_source->value,
                    $record->workforce_trade_id,
                    $record->trade->name,
                    $record->subcontractor_id,
                    $record->subcontractor_name_snapshot,
                );
                $groups[$key]['attended_hours'] += $record->personHours();
            }
        }

        $lines = DailySiteReportLabourLine::query()
            ->with('trade')
            ->where('daily_site_report_id', $report->id)
            ->get();

        foreach ($lines as $line) {
            $tradeName = $line->workforce_trade_id === null
                ? $line->trade_or_role
                : $line->trade->name;
            $key = $this->key(
                $line->labour_source->value,
                $line->workforce_trade_id,
                $line->subcontractor_id,
                $tradeName,
            );
            $groups[$key] ??= $this->group(
                $key,
                $line->labour_source->value,
                $line->workforce_trade_id,
                $tradeName,
                $line->subcontractor_id,
                $line->subcontractor_name,
            );
            $groups[$key]['reported_hours'] += (float) $line->headcount * (float) $line->hours;
        }

        $attendedHours = 0.0;
        $reportedHours = 0.0;
        $hasOverReported = false;
        $hasUnderReported = false;
        $rows = [];

        foreach ($groups as $group) {
            $variance = $group['reported_hours'] - $group['attended_hours'];
            $status = $this->groupStatus($variance, $tolerance);
            $attendedHours += $group['attended_hours'];
            $reportedHours += $group['reported_hours'];
            $hasOverReported = $hasOverReported || $status === DsrLabourAttendanceStatus::OverReported;
            $hasUnderReported = $hasUnderReported || $status === DsrLabourAttendanceStatus::UnderReported;
            $rows[] = [
                ...$group,
                'attended_hours' => round($group['attended_hours'], 2),
                'reported_hours' => round($group['reported_hours'], 2),
                'variance_hours' => round($variance, 2),
                'status' => $status->value,
                'status_label' => $status->label(),
            ];
        }

        $status = match (true) {
            $registers->isEmpty() && $reportedHours > 0 => DsrLabourAttendanceStatus::MissingAttendance,
            $hasOverReported => DsrLabourAttendanceStatus::OverReported,
            $hasUnderReported => DsrLabourAttendanceStatus::UnderReported,
            default => DsrLabourAttendanceStatus::Matched,
        };

        return [
            'status' => $status->value,
            'status_label' => $status->label(),
            'attendance_available' => $registers->isNotEmpty(),
            'register_count' => $registers->count(),
            'registers' => $registers->map(fn (SiteAttendanceRegister $register): array => [
                'id' => $register->id,
                'shift' => $register->shift->value,
                'confirmed_at' => $register->confirmed_at?->toIso8601String(),
            ])->values()->all(),
            'attended_hours' => round($attendedHours, 2),
            'reported_hours' => round($reportedHours, 2),
            'variance_hours' => round($reportedHours - $attendedHours, 2),
            'tolerance_hours' => $tolerance,
            'groups' => $rows,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   labour_source: string,
     *   trade_id: string|null,
     *   trade_name: string,
     *   subcontractor_id: string|null,
     *   subcontractor_name: string|null,
     *   attended_hours: float,
     *   reported_hours: float
     * }
     */
    private function group(
        string $key,
        string $source,
        ?string $tradeId,
        string $tradeName,
        ?string $subcontractorId,
        ?string $subcontractorName,
    ): array {
        return [
            'key' => $key,
            'labour_source' => $source,
            'trade_id' => $tradeId,
            'trade_name' => $tradeName,
            'subcontractor_id' => $subcontractorId,
            'subcontractor_name' => $subcontractorName,
            'attended_hours' => 0.0,
            'reported_hours' => 0.0,
        ];
    }

    private function key(string $source, ?string $tradeId, ?string $subcontractorId, string $tradeName): string
    {
        $trade = $tradeId ?? 'legacy:'.mb_strtolower(mb_trim($tradeName));

        return implode('|', [$source, $trade, $subcontractorId ?? 'none']);
    }

    private function groupStatus(float $variance, float $tolerance): DsrLabourAttendanceStatus
    {
        if ($variance > $tolerance) {
            return DsrLabourAttendanceStatus::OverReported;
        }

        if ($variance < -$tolerance) {
            return DsrLabourAttendanceStatus::UnderReported;
        }

        return DsrLabourAttendanceStatus::Matched;
    }
}
