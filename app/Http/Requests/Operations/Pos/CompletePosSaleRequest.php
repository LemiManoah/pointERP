<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Pos;

use App\Enums\PosPaymentMethod;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryPriceTier;
use App\Models\InventoryStore;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompletePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('pos.sell');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'checkout_key' => ['required', 'uuid'],
            'branch_id' => ['required', 'uuid', Rule::exists((new Branch)->getTable(), 'id')->where('status', 'active')],
            'inventory_store_id' => ['required', 'uuid', Rule::exists((new InventoryStore)->getTable(), 'id')->where('is_active', true)],
            'inventory_price_tier_id' => ['required', 'uuid', Rule::exists((new InventoryPriceTier)->getTable(), 'id')->where('is_active', true)],
            'customer_id' => ['nullable', 'uuid', Rule::exists((new Customer)->getTable(), 'id')->where('status', 'active')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'uuid', Rule::exists((new InventoryItem)->getTable(), 'id')->where('is_active', true)->where('is_for_sale', true)],
            'lines.*.unit_of_measure_id' => ['required', 'uuid', Rule::exists((new UnitOfMeasure)->getTable(), 'id')->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.discount_amount' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'payments' => ['present', 'array'],
            'payments.*.method' => ['required', Rule::enum(PosPaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'payments.*.reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
