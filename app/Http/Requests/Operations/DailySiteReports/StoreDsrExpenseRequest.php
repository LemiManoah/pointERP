<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\DailySiteReports;

use App\Enums\ExpensePayeeType;
use App\Models\Customer;
use App\Models\DailySiteReport;
use App\Models\ExpenseItem;
use App\Models\Staff;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** @phpstan-type DsrExpensePayload array{expense_item_id: string, payee_type: string, customer_id?: string|null, staff_id?: string|null, payee_name?: string|null, quantity?: numeric-string, unit_amount: numeric-string, description?: string|null} */
final class StoreDsrExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $report = $this->route('dailySiteReport');

        return $user instanceof User
            && $report instanceof DailySiteReport
            && Gate::forUser($user)->allows('createExpenseDraft', $report);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'expense_item_id' => ['required', 'uuid', Rule::exists((new ExpenseItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'payee_type' => ['required', Rule::enum(ExpensePayeeType::class)],
            'customer_id' => ['nullable', 'required_if:payee_type,company', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'staff_id' => ['nullable', 'required_if:payee_type,staff', 'uuid', Rule::exists((new Staff)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'payee_name' => ['nullable', 'required_if:payee_type,other', 'string', 'max:180'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit_amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $itemId = $this->input('expense_item_id');
            $item = is_string($itemId)
                ? ExpenseItem::query()->where('tenant_id', resolve(TenantContext::class)->id())->find($itemId)
                : null;

            if ($item instanceof ExpenseItem && $item->has_quantity && ! $this->filled('quantity')) {
                $validator->errors()->add('quantity', 'Enter a quantity for this expense item.');
            }
        }];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'customer_id' => $this->input('customer_id') ?: null,
            'staff_id' => $this->input('staff_id') ?: null,
            'payee_name' => $this->input('payee_name') ?: null,
            'description' => $this->input('description') ?: null,
        ]);
    }
}
