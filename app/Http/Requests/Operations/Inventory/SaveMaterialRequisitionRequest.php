<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\MaterialRequisitionPriority;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveMaterialRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $user = $this->user();
        $branchIds = resolve(BranchContext::class)->accessibleBranchIds($user instanceof User ? $user : null);

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where(fn (Builder $query) => $query->where('tenant_id', $tenantId)->whereIn('id', $branchIds)->where('status', 'active'))],
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where(fn (Builder $query) => $query->where('tenant_id', $tenantId)->where('is_active', true))],
            'project_id' => ['nullable', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'site_id' => ['nullable', 'uuid', Rule::exists((new Site)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'department' => ['nullable', 'string', 'max:120'],
            'required_by_date' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', Rule::enum(MaterialRequisitionPriority::class)],
            'reason' => ['required', 'string', 'max:3000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'uuid', 'distinct', Rule::exists((new InventoryItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query) => $query->where('is_active', true)->where(fn (Builder $query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'lines.*.requested_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.9999'],
            'lines.*.project_activity_id' => ['nullable', 'uuid', Rule::exists((new ProjectActivity)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'lines.*.purpose' => ['nullable', 'string', 'max:255'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
