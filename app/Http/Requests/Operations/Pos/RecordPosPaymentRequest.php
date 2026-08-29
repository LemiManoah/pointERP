<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Pos;

use App\Enums\PosPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordPosPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PosPaymentMethod::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
