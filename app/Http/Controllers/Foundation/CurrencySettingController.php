<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\TenantCurrency;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CurrencySettingController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', TenantCurrency::class);

        $tenant = resolve(TenantContext::class)->current();
        $actor = request()->user();
        abort_unless($actor instanceof User, 403);
        $tenantId = $tenant->id;
        $branchContext = resolve(BranchContext::class);
        $accessibleBranchIds = $branchContext->accessibleBranchIds();
        $canViewAllBranches = $branchContext->canViewAllBranches();
        $defaultBranch = $branchContext->current() ?? $branchContext->operationalDefault();

        $this->ensureDefaultTenantCurrency($tenant);

        $tenantCurrencies = TenantCurrency::query()
            ->with('currency')
            ->where('tenant_id', $tenantId)
            ->orderBy('currency_code')
            ->get()
            ->keyBy('currency_code');

        return Inertia::render('foundation/currency-settings/index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'default_currency_code' => $tenant->default_currency_code,
                'is_multibranch' => $tenant->is_multibranch,
                'multi_currency_enabled' => $tenant->multi_currency_enabled,
            ],
            'defaultBranchId' => $defaultBranch?->id,
            'canManageFacilityWide' => $actor->can('exchange-rates.manage-facility-wide'),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'tenant_enabled' => $currency->code === $tenant->default_currency_code || (bool) $tenantCurrencies->get($currency->code)?->is_enabled,
                    'tenant_default' => $currency->code === $tenant->default_currency_code,
                ]),
            'referenceCurrencies' => Currency::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol ?? '',
                    'decimal_places' => $currency->decimal_places,
                    'is_active' => $currency->is_active,
                ]),
            'branches' => Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->unless($canViewAllBranches, fn (Builder $query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                ]),
            'exchangeRates' => ExchangeRate::query()
                ->with('branch')
                ->where('tenant_id', $tenantId)
                ->unless(
                    $canViewAllBranches,
                    fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $accessibleBranchIds)),
                )
                ->latest('effective_date')
                ->latest()
                ->get()
                ->map(fn (ExchangeRate $rate): array => [
                    'id' => $rate->id,
                    'branch_id' => $rate->branch_id,
                    'branch_name' => $rate->branch?->name,
                    'from_currency_code' => $rate->from_currency_code,
                    'to_currency_code' => $rate->to_currency_code,
                    'rate' => $rate->rate,
                    'effective_date' => $rate->effective_date->toDateString(),
                    'expires_at' => $rate->expires_at?->toDateTimeString(),
                    'status' => $rate->status,
                ]),
        ]);
    }

    private function ensureDefaultTenantCurrency(Tenant $tenant): void
    {
        $setting = TenantCurrency::withTrashed()->firstOrNew([
            'tenant_id' => $tenant->id,
            'currency_code' => $tenant->default_currency_code,
        ]);

        if ($setting->trashed()) {
            $setting->restore();
        }

        $setting->forceFill([
            'is_enabled' => true,
            'is_default' => true,
        ])->save();
    }
}
