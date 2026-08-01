<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessControl\Roles;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRoleRequest extends FormRequest
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
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique((new Role)->getTable(), 'name')->where('guard_name', 'web')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists((new Permission)->getTable(), 'name')],
        ];
    }
}
