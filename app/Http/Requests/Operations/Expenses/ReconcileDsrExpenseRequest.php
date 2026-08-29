<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Expenses;

use App\Models\DailySiteReportCostLine;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReconcileDsrExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'daily_site_report_cost_line_id' => ['required', 'uuid', Rule::exists((new DailySiteReportCostLine)->getTable(), 'id')->where('tenant_id', resolve(TenantContext::class)->id())],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
