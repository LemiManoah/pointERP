<?php

declare(strict_types=1);

namespace App\Http\Requests\Foundation\Countries;

use App\Models\Country;
use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Country $country */
        $country = $this->route('country');

        return [
            'code' => ['required', 'string', 'alpha', 'size:2', Rule::in([$country->code])],
            'name' => ['required', 'string', 'max:120'],
            'iso3_code' => ['required', 'string', 'alpha', 'size:3', Rule::unique((new Country)->getTable(), 'iso3_code')->ignore($country->code, 'code')],
            'default_currency_code' => ['required', 'string', 'size:3', Rule::exists((new Currency)->getTable(), 'code')->where('is_active', true)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper((string) $this->input('code')),
            'iso3_code' => mb_strtoupper((string) $this->input('iso3_code')),
            'default_currency_code' => mb_strtoupper((string) $this->input('default_currency_code')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
