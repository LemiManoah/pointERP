<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Expenses;

use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExpenseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $item = $this->route('expenseItem');

        return [
            'expense_category_id' => ['required', 'uuid', Rule::exists((new ExpenseCategory)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'default_unit_of_measure_id' => ['nullable', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'code' => ['nullable', 'string', 'max:50', Rule::unique((new ExpenseItem)->getTable(), 'code')->where(fn (Builder $query): Builder => $query->where('tenant_id', $tenantId))->ignore($item instanceof ExpenseItem ? $item->id : null)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_quantity' => ['required', 'boolean'],
            'requires_evidence' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
