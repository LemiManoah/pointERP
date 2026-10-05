<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Models\Project;
use App\Models\ProjectEstimate;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type BoqSheet array{id: string, name: string, hidden: bool, rows: array<int, array<string, array{value: string, formula: bool, error: bool}>>}
 * @phpstan-type BoqWorkbook array{name: string, sheets: list<BoqSheet>}
 */
final class PreviewBoqImport
{
    /** @return list<array<string, mixed>> */
    public function existingLines(?ProjectEstimate $estimate): array
    {
        if (! $estimate instanceof ProjectEstimate) {
            return [];
        }

        $estimate->load('lines.resources');

        return $estimate->lines->map(function ($line): array {
            $data = $line->only(['work_item_key', 'site_id', 'unit_of_measure_id', 'boq_reference', 'code', 'name', 'planned_quantity', 'selling_rate', 'estimated_unit_cost', 'notes', 'bill', 'section', 'element', 'description', 'source_document', 'source_sheet', 'source_row']);
            $data['item_type'] = $line->item_type->value;
            $data['resources'] = $line->resources->map(fn ($resource): array => $resource->only(['resource_type', 'inventory_item_id', 'unit_of_measure_id', 'equipment_category_id', 'workforce_trade_id', 'subcontractor_id', 'name', 'quantity_per_work_unit', 'estimated_unit_cost', 'notes']))->all();

            return $data;
        })->all();
    }

    /**
     * @param  BoqWorkbook  $workbook
     * @param  list<array{sheet: string, start_row: int, end_row: int, bill?: string|null, section?: string|null, element?: string|null, reference?: string|null, description: string, unit: string, quantity: string, rate?: string|null, amount?: string|null}>  $mappings
     * @return array<string, mixed>
     */
    public function handle(Project $project, array $workbook, array $mappings, ?ProjectEstimate $base): array
    {
        $existing = $this->existingLines($base);
        $units = UnitOfMeasure::query()->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $project->tenant_id))->get();
        $rows = [];
        $skipped = [];
        $seen = [];
        foreach ($mappings as $mapping) {
            $sheet = null;
            foreach ($workbook['sheets'] as $candidate) {
                if ($candidate['id'] === $mapping['sheet']) {
                    $sheet = $candidate;
                    break;
                }
            }

            if ($sheet === null) {
                throw ValidationException::withMessages(['sheets' => 'Select sheets from the uploaded workbook.']);
            }

            $bill = ($mapping['bill'] ?? '') ?: $sheet['name'];
            $section = $mapping['section'] ?? null;
            $element = $mapping['element'] ?? null;
            $context = [];
            $previousDescription = '';
            $expectElement = false;
            foreach ($sheet['rows'] as $number => $cells) {
                if ($number < $mapping['start_row']) {
                    continue;
                }

                if ($number > $mapping['end_row']) {
                    continue;
                }

                $value = fn (string $field): string => $cells[$mapping[$field] ?? '']['value'] ?? '';
                $description = $value('description');
                $reference = $value('reference');
                if (is_numeric($reference) && str_contains($reference, '.') && mb_strlen($reference) > 12) {
                    $reference = mb_rtrim(mb_rtrim(number_format((float) $reference, 8, '.', ''), '0'), '.');
                }

                $unit = $value('unit');
                $quantity = $value('quantity');
                $rate = $value('rate');
                if ($description === '' && $reference === '' && $quantity === '') {
                    continue;
                }

                if ($description === '' && $unit === '' && $quantity === '' && $reference !== '') {
                    $description = $reference;
                }

                $isTotal = preg_match('/^(?:sub[ -]?total|grand total|total(?:[ :]|$)|carried (?:to|forward)|brought forward|collection|amount carried)/i', $description);
                $isHeader = preg_match('/^(?:description|unit|qty|quantity)$/i', $description);
                if ($isTotal || $isHeader || ($unit === '' && $quantity === '')) {
                    if ($expectElement && $description !== '') {
                        $element = $description;
                        $expectElement = false;
                    } elseif (preg_match('/^ELEMENT\s+(?:NO[ .]*)?\d+/i', $description)) {
                        $element = $description;
                        $expectElement = true;
                        $context = [];
                    }

                    if ($description !== '' && ! $isTotal && ! $isHeader) {
                        $context[] = $description;
                        $context = array_slice($context, -8);
                    }

                    $skipped[] = ['sheet' => $sheet['name'], 'row' => $number, 'description' => $description, 'reason' => $isTotal ? 'Subtotal / carry-forward' : 'Heading / specification'];

                    continue;
                }

                if (count($rows) >= 2000) {
                    throw ValidationException::withMessages(['sheets' => 'Import at most 2,000 BOQ items at a time.']);
                }

                $warnings = [];
                foreach (['quantity', 'rate', 'amount'] as $field) {
                    $cell = $cells[$mapping[$field] ?? ''] ?? null;
                    if ($cell !== null && ($cell['error'] || ($cell['formula'] && $cell['value'] === ''))) {
                        $warnings[] = ucfirst($field).' has an Excel error or no saved formula result. Enter the value before saving.';
                    } elseif ($cell !== null && $cell['formula']) {
                        $warnings[] = ucfirst($field).' uses a saved Excel formula result; verify it is current.';
                    }
                }

                if ($description === '') {
                    $warnings[] = 'Missing item description.';
                }

                if (! is_numeric($quantity) || (float) $quantity <= 0) {
                    $warnings[] = 'Quantity must be a positive number.';
                }

                if ($rate === '') {
                    $warnings[] = 'Unpriced: rate is blank.';
                } elseif (! is_numeric($rate) || (float) $rate < 0) {
                    $warnings[] = 'Rate must be blank or a non-negative number.';
                }

                $amount = $value('amount');
                if (is_numeric($quantity) && is_numeric($rate) && is_numeric($amount)
                    && abs((float) $quantity * (float) $rate - (float) $amount) > 0.01) {
                    $warnings[] = 'Excel amount differs from quantity x rate. The draft will use quantity x rate.';
                }

                $matches = $units->filter(fn (UnitOfMeasure $candidate): bool => in_array(mb_strtolower($unit), array_map(fn ($text): string => mb_strtolower((string) $text), [$candidate->code, $candidate->symbol, $candidate->name]), true) && $unit !== '');
                $unitId = $matches->count() === 1 ? $matches->first()->id : '';
                if (in_array(mb_strtoupper($unit), ['CM', 'SM', 'LM', 'NO', 'ITEM', 'PS'], true)) {
                    $unitId = '';
                }

                if ($unitId === '') {
                    $warnings[] = 'Select a system unit for "'.$unit.'". No conversion is applied.';
                }

                $type = match (mb_strtoupper($unit)) {
                    'PS', 'P.S.', 'PROVISIONAL SUM' => 'provisional_sum',
                    'ITEM', 'SUM', 'LS', 'L.S.', 'LUMP SUM', 'LUMPSUM' => 'lump_sum',
                    default => 'measured',
                };
                if (preg_match('/\bprovisional sum\b/i', $description)) {
                    $warnings[] = 'Review the provisional allowance in the description; it has not been used as a price.';
                }

                $fullDescription = implode("\n", array_filter([...$context, preg_match('/^ditto\b/i', $description) ? 'Previous item: '.$previousDescription : '', $description]));
                if (preg_match('/^ditto\b/i', $description)) {
                    $warnings[] = 'Ditto wording retained with preceding context. Verify the full specification.';
                }

                if (mb_strlen($fullDescription) > 10000) {
                    $warnings[] = 'Specification context exceeds 10,000 characters and was shortened. Review the full source before saving.';
                }

                $line = [
                    'work_item_key' => Str::uuid()->toString(), 'bill' => $bill, 'section' => $section,
                    'element' => $element, 'item_type' => $type, 'site_id' => null,
                    'boq_reference' => $reference ?: null, 'code' => null,
                    'name' => mb_substr($description, 0, 220), 'description' => mb_substr($fullDescription, 0, 10000),
                    'unit_of_measure_id' => $unitId, 'planned_quantity' => $quantity,
                    'selling_rate' => $rate === '' ? null : $rate, 'estimated_unit_cost' => null,
                    'source_document' => $workbook['name'], 'source_sheet' => $sheet['name'], 'source_row' => $number,
                    'notes' => null, 'resources' => [],
                ];
                $identity = $this->identity($line);
                $matching = array_values(array_filter($existing, fn (array $old): bool => $this->identity($old) === $identity));
                $unsupportedMeasurement = str_contains($unit, '%') || preg_match('/percent(?:age)?/i', $unit)
                    || preg_match('/day[ -]?works?/i', $sheet['name']);
                $blocked = count($matching) > 1 || isset($seen[$identity]) || (bool) $unsupportedMeasurement;
                if ($unsupportedMeasurement) {
                    $warnings[] = 'Percentage adjustments and dayworks cannot be imported as physical output. Keep these in the source schedule pending the separate financial/daywork workflow.';
                }

                if ($matching === [] && collect($existing)->contains(fn (array $old): bool => ($old['bill'] ?? '') === $bill && ($old['section'] ?? '') === $section
                    && ($old['element'] ?? '') === $element && ($old['boq_reference'] ?? '') === ($reference ?: null))) {
                    $blocked = true;
                    $warnings[] = 'This reference already exists with different wording. Reconcile it in the draft before importing; no existing identity was guessed.';
                }

                if ($blocked) {
                    $warnings[] = 'Ambiguous or duplicate item reference in this section. Adjust the section mapping or import range before saving.';
                }

                $seen[$identity] = true;
                $change = 'New';
                if (count($matching) === 1) {
                    $old = $matching[0];
                    $line['work_item_key'] = $old['work_item_key'];
                    if ($rate === '' && $old['selling_rate'] !== null) {
                        $line['selling_rate'] = $old['selling_rate'];
                        $warnings[] = 'Blank imported rate: retained the existing price. Clear it explicitly in the draft if required.';
                    }

                    foreach (['site_id', 'code', 'estimated_unit_cost', 'resources', 'notes'] as $field) {
                        $line[$field] = $old[$field];
                    }

                    $change = 'Matched';
                    foreach (['name', 'description', 'planned_quantity', 'selling_rate', 'unit_of_measure_id', 'item_type'] as $field) {
                        if ($field === 'unit_of_measure_id' && $unitId === '') {
                            continue;
                        }

                        $same = is_numeric($old[$field]) && is_numeric($line[$field])
                            ? abs((float) $old[$field] - (float) $line[$field]) < 0.00005
                            : $old[$field] === $line[$field];
                        if (! $same) {
                            $warnings[] = str_replace('_', ' ', $field).': '.($old[$field] ?? 'unpriced').' -> '.($line[$field] ?? 'unpriced');
                            $change = 'Changed';
                        }
                    }

                }

                $rows[] = ['id' => $sheet['id'].':'.$number, 'line' => $line, 'source_unit' => $unit, 'source_amount' => $amount, 'warnings' => $warnings, 'blocked' => $blocked, 'change' => $change];
                $previousDescription = $description;
            }
        }

        $counts = array_count_values(array_map(fn (array $row): string => $this->identity($row['line']), $rows));
        foreach ($rows as &$row) {
            if ($counts[$this->identity($row['line'])] > 1) {
                $row['blocked'] = true;
                $row['warnings'][] = 'This reference occurs more than once in the selected section. Narrow the range or correct the mapping.';
            }
        }

        unset($row);
        $matchedKeys = array_column(array_column($rows, 'line'), 'work_item_key');
        $retained = array_values(array_filter($existing, fn (array $line): bool => ! in_array($line['work_item_key'], $matchedKeys, true)));

        return ['rows' => $rows, 'skipped' => $skipped, 'retained' => array_map(fn (array $line): array => ['name' => $line['name'], 'boq_reference' => $line['boq_reference']], $retained), 'existing' => $existing];
    }

    /** @param array<string, mixed> $line */
    private function identity(array $line): string
    {
        return implode('|', array_map(fn ($value): string => mb_strtolower(mb_trim((string) $value)), [
            $line['bill'] ?? '', $line['section'] ?? '', $line['element'] ?? '',
            $line['boq_reference'] ?? '', $line['name'] ?? '',
        ]));
    }
}
