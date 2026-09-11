<?php

declare(strict_types=1);

namespace App\Actions\Operations\Estimates;

use App\Enums\EstimateResourceType;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkItemResourceTemplate;
use App\Models\WorkItemTemplate;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ImportWorkItemTemplates
{
    private const int MAX_ROWS = 2000;

    public function __construct(private AuditLogger $auditLogger) {}

    /** @return array{imported_templates: int, imported_resources: int} */
    public function handle(Tenant $tenant, UploadedFile $file, User $actor): array
    {
        $path = $file->getRealPath();
        if ($path === false || ($handle = fopen($path, 'r')) === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read the uploaded CSV file.']);
        }

        try {
            $header = fgetcsv($handle, escape: '\\');
            if ($header === false) {
                throw ValidationException::withMessages(['file' => 'The uploaded CSV is empty.']);
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(fn (mixed $value): string => mb_trim(mb_strtolower((string) $value)), $header);
            $required = ['category', 'code', 'activity_name', 'activity_unit', 'default_selling_rate', 'specifications', 'resource_type', 'resource_name', 'resource_unit', 'quantity_per_unit', 'unit_cost', 'notes'];
            $missing = array_values(array_diff(['category', 'activity_name', 'activity_unit'], $header));
            if ($missing !== []) {
                throw ValidationException::withMessages(['file' => 'Missing required columns: '.implode(', ', $missing).'.']);
            }

            /** @var array<string, int|null> $columns */
            $columns = [];
            foreach ($required as $column) {
                $index = array_search($column, $header, true);
                $columns[$column] = $index === false ? null : $index;
            }

            /** @var array<string, UnitOfMeasure> $unitCache */
            $unitCache = [];
            $resolveUnit = function (string $value) use ($tenant, &$unitCache): ?UnitOfMeasure {
                $key = mb_strtolower(mb_trim($value));
                if ($key === '') {
                    return null;
                }

                if (isset($unitCache[$key])) {
                    return $unitCache[$key];
                }

                $unit = UnitOfMeasure::query()
                    ->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                    ->where(function (Builder $query) use ($key): void {
                        $query->whereRaw('LOWER(symbol) = ?', [$key])
                            ->orWhereRaw('LOWER(code) = ?', [$key])
                            ->orWhereRaw('LOWER(name) = ?', [$key]);
                    })
                    ->first();

                if ($unit instanceof UnitOfMeasure) {
                    $unitCache[$key] = $unit;
                }

                return $unit;
            };

            /** @var array<string, array{category: string, code: ?string, name: string, unit_id: string, default_selling_rate: ?string, specifications: ?string, resources: list<array{resource_type: string, name: string, unit_of_measure_id: string, quantity_per_work_unit: string, unit_cost: ?string, notes: ?string}>}> $groups */
            $groups = [];
            $errors = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                $rowNumber++;
                if ($rowNumber > self::MAX_ROWS + 1) {
                    $errors[] = sprintf('The import is limited to %d data rows.', self::MAX_ROWS);
                    break;
                }

                if (array_filter($row, fn (mixed $value): bool => mb_trim((string) $value) !== '') === []) {
                    continue;
                }

                $value = function (string $column) use ($columns, $row): string {
                    $index = $columns[$column] ?? null;

                    return $index === null ? '' : mb_trim($row[$index] ?? '');
                };

                $category = $value('category');
                $code = $value('code') === '' ? null : mb_strtoupper($value('code'));
                $name = $value('activity_name');
                $activityUnit = $resolveUnit($value('activity_unit'));
                $sellingRate = $value('default_selling_rate');

                if ($category === '' || $name === '') {
                    $errors[] = sprintf('Row %d: category and activity_name are required.', $rowNumber);

                    continue;
                }

                if (! $activityUnit instanceof UnitOfMeasure) {
                    $errors[] = sprintf("Row %d: activity unit '%s' is not recognized.", $rowNumber, $value('activity_unit'));

                    continue;
                }

                if ($sellingRate !== '' && (! is_numeric($sellingRate) || (float) $sellingRate < 0)) {
                    $errors[] = sprintf('Row %d: default_selling_rate must be zero or a positive number.', $rowNumber);

                    continue;
                }

                $groupKey = mb_strtolower($code ?? $name);
                $group = [
                    'category' => $category,
                    'code' => $code,
                    'name' => $name,
                    'unit_id' => $activityUnit->id,
                    'default_selling_rate' => $sellingRate === '' ? null : $sellingRate,
                    'specifications' => $value('specifications') === '' ? null : $value('specifications'),
                    'resources' => [],
                ];

                if (isset($groups[$groupKey])) {
                    $existing = $groups[$groupKey];
                    if ($existing['name'] !== $name || $existing['category'] !== $category || $existing['unit_id'] !== $activityUnit->id || $existing['default_selling_rate'] !== $group['default_selling_rate']) {
                        $errors[] = sprintf('Row %d: activity details conflict with an earlier row for %s.', $rowNumber, $code ?? $name);

                        continue;
                    }
                } else {
                    $groups[$groupKey] = $group;
                }

                $resourceValues = [$value('resource_type'), $value('resource_name'), $value('resource_unit'), $value('quantity_per_unit'), $value('unit_cost'), $value('notes')];
                if (array_filter($resourceValues, fn (string $item): bool => $item !== '') === []) {
                    continue;
                }

                $resourceType = mb_strtolower($value('resource_type'));
                $resourceName = $value('resource_name');
                $resourceUnit = $resolveUnit($value('resource_unit'));
                $quantity = $value('quantity_per_unit');
                $unitCost = $value('unit_cost');

                if (! in_array($resourceType, array_column(EstimateResourceType::cases(), 'value'), true)) {
                    $errors[] = sprintf('Row %d: resource_type must be material, labour, equipment, or subcontractor.', $rowNumber);

                    continue;
                }

                if ($resourceName === '' || ! $resourceUnit instanceof UnitOfMeasure || ! is_numeric($quantity) || (float) $quantity <= 0) {
                    $errors[] = sprintf('Row %d: a resource requires a name, recognized unit, and positive quantity_per_unit.', $rowNumber);

                    continue;
                }

                if ($unitCost !== '' && (! is_numeric($unitCost) || (float) $unitCost < 0)) {
                    $errors[] = sprintf('Row %d: unit_cost must be zero or a positive number.', $rowNumber);

                    continue;
                }

                $groups[$groupKey]['resources'][] = [
                    'resource_type' => $resourceType,
                    'name' => $resourceName,
                    'unit_of_measure_id' => $resourceUnit->id,
                    'quantity_per_work_unit' => $quantity,
                    'unit_cost' => $unitCost === '' ? null : $unitCost,
                    'notes' => $value('notes') === '' ? null : $value('notes'),
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 10)]);
        }

        if ($groups === []) {
            throw ValidationException::withMessages(['file' => 'No work activities were found in the file.']);
        }

        $templateCount = 0;
        $resourceCount = 0;

        DB::transaction(function () use ($tenant, $groups, $actor, &$templateCount, &$resourceCount): void {
            foreach ($groups as $item) {
                $lookup = ['tenant_id' => $tenant->id, $item['code'] === null ? 'name' : 'code' => $item['code'] ?? $item['name']];
                $template = WorkItemTemplate::query()->firstOrNew($lookup);
                if (! $template->exists) {
                    $template->setAttribute('created_by', $actor->id);
                }

                $calculatedCost = array_reduce($item['resources'], fn (float $total, array $resource): float => $total + ((float) $resource['quantity_per_work_unit'] * (float) ($resource['unit_cost'] ?? 0)), 0.0);
                $template->fill([
                    'tenant_id' => $tenant->id,
                    'category' => $item['category'],
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'unit_of_measure_id' => $item['unit_id'],
                    'default_selling_rate' => $item['default_selling_rate'],
                    'default_unit_cost' => $calculatedCost > 0 ? (string) round($calculatedCost, 4) : null,
                    'specifications' => $item['specifications'],
                    'is_active' => true,
                    'updated_by' => $actor->id,
                ])->save();

                $template->resources()->delete();
                foreach ($item['resources'] as $sortOrder => $resource) {
                    WorkItemResourceTemplate::query()->create([
                        'tenant_id' => $tenant->id,
                        'work_item_template_id' => $template->id,
                        'resource_type' => EstimateResourceType::from($resource['resource_type']),
                        'name' => $resource['name'],
                        'unit_of_measure_id' => $resource['unit_of_measure_id'],
                        'quantity_per_work_unit' => $resource['quantity_per_work_unit'],
                        'unit_cost' => $resource['unit_cost'],
                        'notes' => $resource['notes'],
                        'sort_order' => $sortOrder,
                    ]);
                    $resourceCount++;
                }

                $templateCount++;
            }

            $this->auditLogger->record('work_item_templates.imported', $tenant, $actor, properties: ['templates_count' => $templateCount, 'resources_count' => $resourceCount]);
        });

        return ['imported_templates' => $templateCount, 'imported_resources' => $resourceCount];
    }
}
