<?php

declare(strict_types=1);

namespace App\Actions\Operations\Boq;

use App\Models\BoqProgressEntry;
use App\Models\DailySiteReport;
use App\Models\DailySiteReportCorrection;
use App\Models\DailySiteReportWorkLine;
use App\Models\Project;
use App\Models\ProjectActivity;
use Brick\Math\BigDecimal;

final readonly class ReconcileProjectProgress
{
    /** @return list<array{type: string, record: string, detail: string}> */
    public function handle(Project $project): array
    {
        $findings = [];
        $activities = ProjectActivity::query()->withTrashed()->where('project_id', $project->id)->get()->keyBy('id');
        $entries = BoqProgressEntry::query()->where('project_id', $project->id)->get();
        $byActivity = $entries->groupBy('project_activity_id');
        $byLine = $entries->groupBy('daily_site_report_work_line_id');
        $lines = DailySiteReportWorkLine::query()->where('tenant_id', $project->tenant_id)
            ->whereHas('report', fn ($query) => $query->where('project_id', $project->id)->whereIn('status', [DailySiteReport::STATUS_APPROVED, DailySiteReport::STATUS_ARCHIVED]))
            ->get()->keyBy('id');

        $correctionDeltas = [];
        $correctionSources = [];
        $corrections = DailySiteReportCorrection::query()->where('status', DailySiteReportCorrection::STATUS_APPROVED)
            ->whereHas('report', fn ($query) => $query->where('project_id', $project->id))->get();
        foreach ($corrections as $correction) {
            foreach (($correction->new_values['work_adjustments'] ?? []) as $adjustment) {
                $lineId = $adjustment['line_id'];
                $delta = BigDecimal::of($adjustment['quantity_delta'] ?? '0');
                if ($delta->isZero()) {
                    continue;
                }

                $correctionDeltas[$lineId] = ($correctionDeltas[$lineId] ?? BigDecimal::zero())->plus($delta);
                $correctionSources['correction:'.$correction->id.':'.$lineId] = (string) $delta;
            }
        }

        foreach ($correctionSources as $key => $delta) {
            $entry = $entries->firstWhere('source_key', $key);
            if (! $entry || ! BigDecimal::of($entry->quantity)->isEqualTo($delta)) {
                $findings[] = ['type' => 'Correction mismatch', 'record' => $key, 'detail' => 'Approved correction is missing or has a different quantity in the progress ledger.'];
            }
        }

        foreach ($entries as $entry) {
            if (str_starts_with($entry->source_key, 'correction:') && ! isset($correctionSources[$entry->source_key])) {
                $findings[] = ['type' => 'Unapproved correction', 'record' => $entry->id, 'detail' => 'No approved correction supports this entry.'];
            }

            if (str_starts_with($entry->source_key, 'legacy:')) {
                $findings[] = ['type' => 'Legacy balance', 'record' => $entry->id,
                    'detail' => $entry->quantity.' '.$entry->unit.' requires review against original site records.'];
            } elseif (! $lines->has($entry->getAttribute('daily_site_report_work_line_id'))) {
                $findings[] = ['type' => 'Missing approved source', 'record' => $entry->id,
                    'detail' => 'Progress entry has no matching approved DSR work line.'];
            }

            $activity = $activities->get($entry->project_activity_id);
            if (! $activity || $activity->boq_item_id !== $entry->boq_item_id || $activity->unit !== $entry->unit
                || $activity->progress_method !== 'measured') {
                $findings[] = ['type' => 'Invalid activity link', 'record' => $entry->id,
                    'detail' => 'Progress entry does not match a measured activity, BOQ item and unit.'];
            }
        }

        foreach ($lines as $line) {
            $activity = $activities->get($line->getAttribute('project_activity_id'));
            $posted = $byLine->get($line->id, collect());
            if (! $line->counts_towards_boq && $activity?->progress_method === 'supporting' && $posted->isEmpty()) {
                continue;
            }

            if (! $activity || ! $line->boq_item_id || $line->boq_item_id !== $activity->boq_item_id
                || $line->getAttribute('unit') !== $activity->unit || ! $line->counts_towards_boq) {
                $findings[] = ['type' => 'Unlinked or incompatible output', 'record' => $line->id,
                    'detail' => 'Review the approved DSR activity, BOQ link, unit and output classification.'];

                continue;
            }

            $quantity = BigDecimal::zero();
            foreach ($posted as $entry) {
                $quantity = $quantity->plus($entry->quantity);
                if ($entry->boq_item_id !== $line->boq_item_id || $entry->project_activity_id !== $activity->id
                    || $entry->unit !== $line->getAttribute('unit')) {
                    $findings[] = ['type' => 'Source link mismatch', 'record' => $entry->id,
                        'detail' => 'Progress entry differs from its approved DSR activity, BOQ item or unit.'];
                }
            }

            $expected = BigDecimal::of($line->quantity ?? '0')->plus($correctionDeltas[$line->id] ?? '0');
            if ($posted->isEmpty() || ! $quantity->isEqualTo($expected)) {
                $findings[] = ['type' => 'Source quantity mismatch', 'record' => $line->id,
                    'detail' => 'Approved source with corrections: '.$expected.'; progress entries: '.$quantity.'.'];
            }
        }

        foreach ($activities as $activity) {
            if (! $activity->boq_item_id) {
                continue;
            }

            $quantity = BigDecimal::zero();
            foreach ($byActivity->get($activity->id, collect()) as $entry) {
                $quantity = $quantity->plus($entry->quantity);
            }

            if (! $quantity->isEqualTo($activity->approved_quantity ?? '0')) {
                $findings[] = ['type' => 'Activity quantity mismatch', 'record' => $activity->id,
                    'detail' => $activity->name.': saved '.($activity->approved_quantity ?? '0').'; progress entries '.$quantity.'.'];
            }
        }

        return $findings;
    }
}
