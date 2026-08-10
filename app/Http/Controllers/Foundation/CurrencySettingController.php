<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\CurrencySettings\SaveBranchCurrency;
use App\Actions\Foundation\CurrencySettings\ToggleTenantCurrency;
use App\Http\Requests\Foundation\CurrencySettings\SaveBranchCurrencyRequest;
use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\Currency;
use App\Models\TenantCurrency;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class CurrencySettingController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', TenantCurrency::class);

        $tenant = resolve(TenantContext::class)->current();

        $tenantCurrencies = TenantCurrency::query()
            ->with('currency')
            ->where('tenant_id', $tenant->id)
            ->orderBy('currency_code')
            ->get()
            ->keyBy('currency_code');

        return Inertia::render('foundation/currency-settings/index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'default_currency_code' => $tenant->default_currency_code,
                'multi_currency_enabled' => $tenant->multi_currency_enabled,
            ],
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'tenant_enabled' => (bool) $tenantCurrencies->get($currency->code)?->is_enabled,
                    'tenant_default' => $currency->code === $tenant->default_currency_code,
                ]),
            'branches' => Branch::query()
                ->with('enabledCurrencies.currency')
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'default_currency_code' => $branch->default_currency_code,
                    'currencies' => $branch->enabledCurrencies
                        ->sortBy('currency_code')
                        ->values()
                        ->map(fn (BranchCurrency $setting): array => [
                            'id' => $setting->id,
                            'currency_code' => $setting->currency_code,
                            'currency_name' => $setting->currency->name,
                            'is_enabled' => $setting->is_enabled,
                            'is_default_transaction_currency' => $setting->is_default_transaction_currency,
                            'can_receive' => $setting->can_receive,
                            'can_pay' => $setting->can_pay,
                        ]),
                ]),
        ]);
    }

    public function toggleTenantCurrency(Currency $currency, ToggleTenantCurrency $action): RedirectResponse
    {
        Gate::authorize('viewAny', TenantCurrency::class);

        try {
            $setting = $action->handle($currency);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $setting->is_enabled ? 'Tenant currency enabled.' : 'Tenant currency disabled.',
        ]);

        return back();
    }

    public function storeBranchCurrency(SaveBranchCurrencyRequest $request, SaveBranchCurrency $action): RedirectResponse
    {
        Gate::authorize('viewAny', BranchCurrency::class);

        /** @var array{branch_id: string, currency_code: string, is_enabled: bool, is_default_transaction_currency: bool, can_receive: bool, can_pay: bool} $data */
        $data = $request->validated();

        try {
            $action->handle($data);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $invalidArgumentException->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Branch currency setting saved.']);

        return back();
    }
}
