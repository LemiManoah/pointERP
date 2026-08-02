<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Foundation\Http\FormRequest;

final class SelectCurrentBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid'],
        ];
    }
}
