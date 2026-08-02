<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl\Users;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
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
        return [
            'staff_id' => [
                'required',
                'uuid',
                Rule::exists((new Staff)->getTable(), 'id')
                    ->where('tenant_id', resolve(TenantContext::class)->id())
                    ->where('status', 'active'),
                Rule::unique((new User)->getTable(), 'staff_id'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'is_director' => ['sometimes', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists((new Role)->getTable(), 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists((new Permission)->getTable(), 'name')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_director' => $this->boolean('is_director'),
        ]);
    }
}
