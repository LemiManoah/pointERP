<?php

declare(strict_types=1);

namespace App\Http\Requests\Foundation\Currencies;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCurrencyRequest extends FormRequest
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
        /** @var Currency $currency */
        $currency = $this->route('currency');

        return [
            'code' => ['required', 'string', 'alpha', 'size:3', Rule::in([$currency->code])],
            'name' => ['required', 'string', 'max:120'],
            'symbol' => ['nullable', 'string', 'max:12'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper((string) $this->input('code')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
