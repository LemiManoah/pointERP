<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ProjectBudgetReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('projects.view-all');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/']];
    }
}
