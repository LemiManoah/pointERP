<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\Currencies\CreateCurrency;
use App\Actions\Foundation\Currencies\ToggleCurrencyStatus;
use App\Actions\Foundation\Currencies\UpdateCurrency;
use App\Http\Requests\Foundation\Currencies\StoreCurrencyRequest;
use App\Http\Requests\Foundation\Currencies\UpdateCurrencyRequest;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CurrencyController
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('foundation.currencies.manage'), 403);

        return Inertia::render('foundation/currencies/index', [
            'currencies' => Currency::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => $currency->decimal_places,
                    'is_active' => $currency->is_active,
                ]),
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('foundation.currencies.manage'), 403);

        return Inertia::render('foundation/currencies/create');
    }

    public function store(StoreCurrencyRequest $request, CreateCurrency $action): RedirectResponse
    {
        abort_unless($request->user()?->can('foundation.currencies.manage'), 403);

        /** @var array{code: string, name: string, symbol?: string|null, decimal_places: int|string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Currency created.',
        ]);

        return to_route('foundation.currencies.index');
    }

    public function edit(Currency $currency): Response
    {
        abort_unless(auth()->user()?->can('foundation.currencies.manage'), 403);

        return Inertia::render('foundation/currencies/edit', [
            'currency' => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
                'is_active' => $currency->is_active,
            ],
        ]);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency, UpdateCurrency $action): RedirectResponse
    {
        abort_unless($request->user()?->can('foundation.currencies.manage'), 403);

        /** @var array{code: string, name: string, symbol?: string|null, decimal_places: int|string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($currency, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Currency updated.',
        ]);

        return to_route('foundation.currencies.index');
    }

    public function destroy(Currency $currency, ToggleCurrencyStatus $action): RedirectResponse
    {
        abort_unless(auth()->user()?->can('foundation.currencies.manage'), 403);

        $action->handle($currency);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $currency->is_active ? 'Currency activated.' : 'Currency deactivated.',
        ]);

        return to_route('foundation.currencies.index');
    }
}
