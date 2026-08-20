<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Equipment;
use App\Models\InventoryBatch;
use App\Models\Project;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreInventoryStockMovementRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'movement_type' => ['required', Rule::in([InventoryMovementType::OpeningBalance->value, InventoryMovementType::Receipt->value, InventoryMovementType::Issue->value, InventoryMovementType::Return->value, InventoryMovementType::Adjustment->value])],
            'original_quantity' => ['required', 'numeric', 'gt:0'],
            'adjustment_direction' => ['nullable', Rule::requiredIf($this->input('movement_type') === InventoryMovementType::Adjustment->value), Rule::in(['increase', 'decrease'])],
            'original_unit_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))->where('is_active', true)],
            'inventory_batch_id' => ['nullable', 'uuid', Rule::exists((new InventoryBatch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'project_id' => ['nullable', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['nullable', 'uuid', Rule::exists((new Equipment)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'source_key' => ['required', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (! $this->filled('source_key')) {
            $this->merge(['source_key' => 'manual:'.Str::uuid()->toString()]);
        }
    }
}
