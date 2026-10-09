<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Inventory;

use App\Enums\UnitDimension;
use App\Models\UnitOfMeasure;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUnitOfMeasureRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $unit = $this->route('unitOfMeasure');
        $codeRules = $unit instanceof UnitOfMeasure
            ? ['required', 'string', 'max:30', Rule::unique('unit_of_measures', 'code')->where('tenant_id', resolve(TenantContext::class)->id())->ignore($unit->id)]
            : ['prohibited'];

        return [
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'quantity_dimension' => ['required', Rule::enum(UnitDimension::class)],
            'is_base_unit' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper((string) $this->input('code'))]);
    }
}
