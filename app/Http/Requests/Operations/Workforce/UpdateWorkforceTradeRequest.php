<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Workforce;

use App\Enums\WorkforceTradeCategory;
use App\Models\WorkforceTrade;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWorkforceTradeRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $trade = $this->route('workforceTrade');
        $tradeId = $trade instanceof WorkforceTrade ? $trade->id : null;

        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique('workforce_trades', 'code')->where('tenant_id', resolve(TenantContext::class)->id())->ignore($tradeId)],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(WorkforceTradeCategory::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(mb_trim((string) $this->input('code')))]);
    }
}
