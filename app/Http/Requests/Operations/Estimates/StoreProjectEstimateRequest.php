<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use App\Enums\EstimateResourceType;
use App\Models\InventoryItem;
use App\Models\Site;
use App\Models\TenantCurrency;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @phpstan-type EstimateResourcePayload array{resource_type: string, inventory_item_id?: string|null, unit_of_measure_id?: string|null, name: string, quantity_per_work_unit: numeric-string, estimated_unit_cost?: numeric-string|null, notes?: string|null}
 * @phpstan-type EstimateLinePayload array{work_item_key?: string|null, site_id?: string|null, unit_of_measure_id: string, boq_reference?: string|null, code?: string|null, name: string, planned_quantity: numeric-string, selling_rate?: numeric-string|null, estimated_unit_cost?: numeric-string|null, notes?: string|null, resources?: list<EstimateResourcePayload>}
 * @phpstan-type ProjectEstimatePayload array{title: string, currency_code: string, notes?: string|null, lines: list<EstimateLinePayload>}
 */
final class StoreProjectEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $unitRule = Rule::exists((new UnitOfMeasure)->getTable(), 'id')
            ->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->where('is_active', true);

        return [
            'title' => ['required', 'string', 'max:160'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists((new TenantCurrency)->getTable(), 'currency_code')->where('tenant_id', $tenantId)->where('is_enabled', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.work_item_key' => ['nullable', 'uuid', 'distinct'],
            'lines.*.site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', $unitRule],
            'lines.*.boq_reference' => ['nullable', 'string', 'max:80'],
            'lines.*.code' => ['nullable', 'string', 'max:80'],
            'lines.*.name' => ['required', 'string', 'max:220'],
            'lines.*.planned_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.selling_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:3000'],
            'lines.*.resources' => ['sometimes', 'array', 'max:100'],
            'lines.*.resources.*.resource_type' => ['required', Rule::enum(EstimateResourceType::class)],
            'lines.*.resources.*.inventory_item_id' => ['nullable', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.resources.*.unit_of_measure_id' => ['nullable', 'uuid', $unitRule],
            'lines.*.resources.*.name' => ['required', 'string', 'max:220'],
            'lines.*.resources.*.quantity_per_work_unit' => ['required', 'numeric', 'gt:0'],
            'lines.*.resources.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.resources.*.notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))->map(function (mixed $line): mixed {
            if (! is_array($line)) {
                return $line;
            }

            foreach (['work_item_key', 'site_id', 'boq_reference', 'code', 'selling_rate', 'estimated_unit_cost', 'notes'] as $field) {
                $line[$field] = ($line[$field] ?? null) === '' ? null : ($line[$field] ?? null);
            }

            $line['resources'] = collect((array) ($line['resources'] ?? []))->map(function (mixed $resource): mixed {
                if (! is_array($resource)) {
                    return $resource;
                }

                foreach (['inventory_item_id', 'unit_of_measure_id', 'estimated_unit_cost', 'notes'] as $field) {
                    $resource[$field] = ($resource[$field] ?? null) === '' ? null : ($resource[$field] ?? null);
                }

                return $resource;
            })->all();

            return $line;
        })->all();

        $this->merge([
            'currency_code' => mb_strtoupper((string) $this->input('currency_code')),
            'notes' => $this->input('notes') === '' ? null : $this->input('notes'),
            'lines' => $lines,
        ]);
    }
}
