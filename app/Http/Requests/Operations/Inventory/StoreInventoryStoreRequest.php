<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\InventoryStoreType;
use App\Models\Branch;
use App\Models\EquipmentLocation;
use App\Models\Project;
use App\Models\Site;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryStoreRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active'), Rule::in(resolve(BranchContext::class)->accessibleBranchIds())],
            'equipment_location_id' => ['nullable', 'uuid', Rule::exists((new EquipmentLocation)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'project_id' => ['nullable', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'code' => ['required', 'string', 'max:50', Rule::unique('inventory_stores', 'code')->where('tenant_id', $tenantId)->ignore($this->route('inventoryStore')?->id)],
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::enum(InventoryStoreType::class)],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper((string) $this->input('code')),
            'equipment_location_id' => $this->input('equipment_location_id') ?: null,
            'project_id' => $this->input('project_id') ?: null,
            'site_id' => $this->input('site_id') ?: null,
        ]);
    }
}
