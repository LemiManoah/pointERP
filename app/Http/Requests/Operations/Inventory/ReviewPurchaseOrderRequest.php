<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approve', 'return', 'reject'])], 'reason' => ['nullable', 'required_unless:decision,approve', 'string', 'max:2000']];
    }
}
