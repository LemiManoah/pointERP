<?php

declare(strict_types=1);

namespace App\Http\Controllers\Foundation;

use App\Actions\Foundation\Countries\CreateCountry;
use App\Actions\Foundation\Countries\ToggleCountryStatus;
use App\Actions\Foundation\Countries\UpdateCountry;
use App\Http\Requests\Foundation\Countries\StoreCountryRequest;
use App\Http\Requests\Foundation\Countries\UpdateCountryRequest;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CountryController
{
    public function index(): Response
    {
        return Inertia::render('foundation/countries/index', [
            'countries' => Country::query()
                ->with('defaultCurrency')
                ->orderBy('name')
                ->get()
                ->map(fn (Country $country): array => [
                    'code' => $country->code,
                    'name' => $country->name,
                    'iso3_code' => $country->iso3_code,
                    'default_currency_code' => $country->default_currency_code,
                    'default_currency_name' => $country->defaultCurrency->name,
                    'is_active' => $country->is_active,
                ]),
            'currencies' => $this->activeCurrencies(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('foundation/countries/create', [
            'currencies' => $this->activeCurrencies(),
        ]);
    }

    public function store(StoreCountryRequest $request, CreateCountry $action): RedirectResponse
    {
        /** @var array{code: string, name: string, iso3_code: string, default_currency_code: string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($data);

        return to_route('foundation.countries.index');
    }

    public function edit(Country $country): Response
    {
        return Inertia::render('foundation/countries/edit', [
            'country' => [
                'code' => $country->code,
                'name' => $country->name,
                'iso3_code' => $country->iso3_code,
                'default_currency_code' => $country->default_currency_code,
                'is_active' => $country->is_active,
            ],
            'currencies' => $this->activeCurrencies(),
        ]);
    }

    public function update(UpdateCountryRequest $request, Country $country, UpdateCountry $action): RedirectResponse
    {
        /** @var array{code: string, name: string, iso3_code: string, default_currency_code: string, is_active?: bool} $data */
        $data = $request->validated();

        $action->handle($country, $data);

        return to_route('foundation.countries.index');
    }

    public function destroy(Country $country, ToggleCountryStatus $action): RedirectResponse
    {
        $action->handle($country);

        return to_route('foundation.countries.index');
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function activeCurrencies(): array
    {
        return Currency::query()
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn (Currency $currency): array => [
                'code' => $currency->code,
                'name' => $currency->name,
            ])
            ->all();
    }
}
