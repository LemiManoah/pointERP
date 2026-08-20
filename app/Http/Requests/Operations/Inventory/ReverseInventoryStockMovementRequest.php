<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseInventoryStockMovementRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
