<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $today = CarbonImmutable::now($this->user()->tenant->timezone ?: config('app.timezone'))->toDateString();

        return [
            'period' => ['sometimes', 'required', Rule::in(['today', 'last7', 'month', 'quarter', 'range'])],
            'from' => ['exclude_unless:period,range', 'required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'to' => ['exclude_unless:period,range', 'required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$today],
            'currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('period') !== 'range' || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 365) {
                $validator->errors()->add('to', 'Choose a range of up to one year.');
            }
        }];
    }
}
