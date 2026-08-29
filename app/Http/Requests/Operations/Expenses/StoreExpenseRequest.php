<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Expenses;

use App\Enums\ExpensePayeeType;
use App\Enums\ExpensePaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\Staff;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @phpstan-type ExpenseLinePayload array{expense_item_id: string, project_id?: string|null, site_id?: string|null, project_activity_id?: string|null, description?: string|null, quantity: numeric-string, unit_amount: numeric-string}
 * @phpstan-type ExpensePayload array{branch_id: string, expense_date: string, payee_type: string, customer_id?: string|null, staff_id?: string|null, payee_name?: string|null, currency_code: string, description?: string|null, reference?: string|null, lines: list<ExpenseLinePayload>, initial_payment_amount?: numeric-string|null, initial_payment_method?: string|null, initial_payment_reference?: string|null}
 */
final class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? Gate::forUser($user)->allows('update', $expense)
            : Gate::forUser($user)->allows('create', Expense::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();
        $tenant = resolve(TenantContext::class)->current();
        $user = $this->user();
        abort_unless($user instanceof User, 403);
        $branchContext = resolve(BranchContext::class);
        $branchIds = $branchContext->accessibleBranchIds($user);
        if (! $user->can('expenses.change-branch')) {
            $expense = $this->route('expense');
            $lockedBranch = $expense instanceof Expense
                ? $expense->branch_id
                : ($branchContext->current($user) ?? $branchContext->operationalDefault($user))?->id;
            $branchIds = is_string($lockedBranch) ? [$lockedBranch] : [];
        }

        $projectIds = Project::query()->visibleTo($user)->whereIn('branch_id', $branchIds)->pluck('id')->all();
        $siteIds = Site::query()->whereIn('project_id', $projectIds)->pluck('id')->all();
        $activityIds = ProjectActivity::query()->whereIn('project_id', $projectIds)->pluck('id')->all();
        $currencyCodes = TenantCurrency::query()->where('is_enabled', true)->pluck('currency_code')->all();
        if (! $tenant->multi_currency_enabled) {
            $selectedBranch = Branch::query()->find($this->input('branch_id'));
            $currencyCodes = $selectedBranch instanceof Branch ? [$selectedBranch->default_currency_code] : [$tenant->default_currency_code];
        }

        return [
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('tenant_id', $tenantId)->whereIn('id', $branchIds)],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payee_type' => ['required', Rule::enum(ExpensePayeeType::class)],
            'customer_id' => ['nullable', 'required_if:payee_type,company', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'staff_id' => ['nullable', 'required_if:payee_type,staff', 'uuid', Rule::exists((new Staff)->getTable(), 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'payee_name' => ['nullable', 'required_if:payee_type,other', 'string', 'max:180'],
            'currency_code' => ['required', 'string', 'size:3', Rule::in($currencyCodes)],
            'description' => ['nullable', 'string', 'max:3000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.expense_item_id' => ['required', 'uuid', Rule::exists((new ExpenseItem)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'lines.*.project_id' => ['nullable', 'uuid', Rule::in($projectIds)],
            'lines.*.site_id' => ['nullable', 'uuid', Rule::in($siteIds)],
            'lines.*.project_activity_id' => ['nullable', 'uuid', Rule::in($activityIds)],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_amount' => ['required', 'numeric', 'gt:0'],
            'initial_payment_amount' => ['nullable', 'numeric', 'gt:0'],
            'initial_payment_method' => ['nullable', 'required_with:initial_payment_amount', Rule::enum(ExpensePaymentMethod::class)],
            'initial_payment_reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $branchId = $this->string('branch_id')->toString();

            foreach ((array) $this->input('lines', []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $project = is_string($line['project_id'] ?? null) ? Project::query()->find($line['project_id']) : null;
                $site = is_string($line['site_id'] ?? null) ? Site::query()->find($line['site_id']) : null;
                $activity = is_string($line['project_activity_id'] ?? null) ? ProjectActivity::query()->find($line['project_activity_id']) : null;

                if ($project instanceof Project && $project->branch_id !== $branchId) {
                    $validator->errors()->add(sprintf('lines.%s.project_id', $index), 'The project must belong to the expense branch.');
                }

                if ($site instanceof Site && (! $project instanceof Project || $site->project_id !== $project->id)) {
                    $validator->errors()->add(sprintf('lines.%s.site_id', $index), 'The site must belong to the selected project.');
                }

                if ($activity instanceof ProjectActivity && (! $project instanceof Project || $activity->project_id !== $project->id || ($site instanceof Site && $activity->site_id !== null && $activity->site_id !== $site->id))) {
                    $validator->errors()->add(sprintf('lines.%s.project_activity_id', $index), 'The Work item must belong to the selected project and site.');
                }
            }

            $expense = $this->route('expense');
            $duplicate = Expense::query()
                ->when($expense instanceof Expense, fn (Builder $query): Builder => $query->whereKeyNot($expense->id))
                ->where('customer_id', $this->input('customer_id'))
                ->whereNotNull('reference')
                ->where('reference', $this->input('reference'))
                ->exists();
            if ($this->filled('customer_id') && $this->filled('reference') && $duplicate) {
                $validator->errors()->add('reference', 'This company reference is already used on another expense.');
            }
        }];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => mb_strtoupper((string) $this->input('currency_code')),
            'customer_id' => $this->input('customer_id') ?: null,
            'staff_id' => $this->input('staff_id') ?: null,
            'payee_name' => $this->input('payee_name') ?: null,
            'description' => $this->input('description') ?: null,
            'reference' => $this->input('reference') ?: null,
            'initial_payment_amount' => $this->input('initial_payment_amount') ?: null,
            'initial_payment_method' => $this->input('initial_payment_method') ?: null,
            'initial_payment_reference' => $this->input('initial_payment_reference') ?: null,
        ]);
    }
}
