<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpensePayeeType;
use App\Enums\ExpensePaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\Staff;
use App\Models\TenantCurrency;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class ExpenseFormOptions
{
    public function __construct(private BranchContext $branchContext, private TenantContext $tenantContext) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $branches = $this->branchContext->accessibleBranches($user);
        $defaultBranch = $this->branchContext->current($user) ?? $this->branchContext->operationalDefault($user);
        $projects = Project::query()->visibleTo($user)->whereNotIn('status', ['closed', 'archived'])->orderBy('name')->get();
        $projectIds = $projects->pluck('id');
        $tenant = $this->tenantContext->current();

        return [
            'branches' => $branches->map(fn (Branch $branch): array => ['value' => $branch->id, 'label' => $branch->name, 'currency_code' => $branch->default_currency_code])->values(),
            'defaultBranchId' => $defaultBranch?->id,
            'canChangeBranch' => $user->can('expenses.change-branch') && $branches->count() > 1,
            'companies' => Customer::query()->visibleTo($user)->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'code'])->map(fn (Customer $company): array => ['value' => $company->id, 'label' => $company->name, 'branch_id' => $company->branch_id]),
            'staff' => Staff::query()->whereIn('branch_id', $branches->pluck('id'))->where('status', 'active')->orderBy('name')->get(['id', 'branch_id', 'name', 'staff_number'])->map(fn (Staff $staff): array => ['value' => $staff->id, 'label' => $staff->name, 'branch_id' => $staff->branch_id]),
            'projects' => $projects->map(fn (Project $project): array => ['value' => $project->id, 'label' => $project->reference.' - '.$project->name, 'branch_id' => $project->branch_id]),
            'sites' => Site::query()->whereIn('project_id', $projectIds)->whereIn('status', ['planned', 'active', 'suspended'])->orderBy('name')->get(['id', 'project_id', 'name'])->map(fn (Site $site): array => ['value' => $site->id, 'label' => $site->name, 'project_id' => $site->project_id]),
            'workItems' => ProjectActivity::query()->whereIn('project_id', $projectIds)->where('status', 'active')->orderBy('name')->get(['id', 'project_id', 'site_id', 'name'])->map(fn (ProjectActivity $activity): array => ['value' => $activity->id, 'label' => $activity->name, 'project_id' => $activity->project_id, 'site_id' => $activity->site_id]),
            'categories' => ExpenseCategory::query()->orderBy('name')->get()->map(fn (ExpenseCategory $category): array => ['id' => $category->id, 'code' => $category->code, 'name' => $category->name, 'description' => $category->description, 'requires_evidence' => $category->requires_evidence, 'is_active' => $category->is_active]),
            'expenseItems' => ExpenseItem::query()->with(['category', 'defaultUnit'])->orderBy('name')->get()->map(fn (ExpenseItem $item): array => ['id' => $item->id, 'expense_category_id' => $item->expense_category_id, 'code' => $item->code, 'name' => $item->name, 'description' => $item->description, 'default_unit_of_measure_id' => $item->default_unit_of_measure_id, 'unit' => $item->default_unit_of_measure_id !== null ? ($item->defaultUnit->symbol ?? $item->defaultUnit->name) : null, 'requires_evidence' => $item->requires_evidence || $item->category->requires_evidence, 'is_active' => $item->is_active]),
            'units' => UnitOfMeasure::query()->where(fn (Builder $query): Builder => $query->whereNull('tenant_id')->orWhere('tenant_id', $this->tenantContext->id()))->where('is_active', true)->orderBy('name')->get(['id', 'name', 'symbol'])->map(fn (UnitOfMeasure $unit): array => ['value' => $unit->id, 'label' => $unit->symbol ? $unit->name.' ('.$unit->symbol.')' : $unit->name]),
            'currencies' => TenantCurrency::query()->with('currency')->where('is_enabled', true)->unless($tenant->multi_currency_enabled, fn (Builder $query): Builder => $query->where('is_default', true))->orderByDesc('is_default')->get()->map(fn (TenantCurrency $currency): array => ['value' => $currency->currency_code, 'label' => $currency->currency->name.' ('.$currency->currency_code.')']),
            'payeeTypes' => collect(ExpensePayeeType::cases())->map(fn (ExpensePayeeType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            'paymentMethods' => collect(ExpensePaymentMethod::cases())->map(fn (ExpensePaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
        ];
    }
}
