<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Projects;

use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignProjectUsersRequest extends FormRequest
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
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'users' => ['array'],
            'users.*.user_id' => ['required', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'users.*.role' => ['nullable', 'string', 'max:80'],
            'users.*.can_manage' => ['boolean'],
        ];
    }
}
