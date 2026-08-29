<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Expenses;

use App\Models\ExpenseCategory;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $category = $this->route('expenseCategory');

        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique((new ExpenseCategory)->getTable(), 'code')->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))->ignore($category instanceof ExpenseCategory ? $category->id : null)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_evidence' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
