<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Sites;

use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignSiteUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'users' => ['array'],
            'users.*.user_id' => ['required', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'users.*.role' => ['nullable', 'string', 'max:80'],
            'users.*.can_submit_dsr' => ['boolean'],
            'users.*.can_review_dsr' => ['boolean'],
        ];
    }
}
