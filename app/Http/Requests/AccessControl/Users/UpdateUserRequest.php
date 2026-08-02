<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl\Users;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');
        $tenantId = resolve(TenantContext::class)->id();

        return [
            'staff_id' => [
                'required',
                'uuid',
                Rule::exists((new Staff)->getTable(), 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'),
                Rule::unique((new User)->getTable(), 'staff_id')->ignore($user->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'is_director' => ['sometimes', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists((new Role)->getTable(), 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists((new Permission)->getTable(), 'name')],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists((new Branch)->getTable(), 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'),
            ],
            'default_branch_id' => ['required', 'uuid', Rule::in($this->input('branch_ids', []))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $branchIds = collect($this->input('branch_ids', []))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_director' => $this->boolean('is_director'),
            'branch_ids' => $branchIds,
            'default_branch_id' => $this->input('default_branch_id') ?: ($branchIds[0] ?? null),
        ]);
    }
}
