<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use App\Models\WorkItemCategory;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWorkItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $category = $this->route('workItemCategory');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique((new WorkItemCategory)->getTable(), 'name')->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))->ignore($category instanceof WorkItemCategory ? $category->id : null)],
            'code' => ['nullable', 'string', 'max:80', Rule::unique((new WorkItemCategory)->getTable(), 'code')->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))->ignore($category instanceof WorkItemCategory ? $category->id : null)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
