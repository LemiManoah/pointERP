<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Sites;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSiteRequest extends FormRequest
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
            'project_id' => ['required', 'uuid', Rule::exists((new Project)->getTable(), 'id')->where('tenant_id', $tenantId)],
            'reference' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:180'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'manager_id' => ['nullable', 'uuid', Rule::exists((new User)->getTable(), 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'reporting_deadline' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'string', Rule::in(['planned', 'active', 'suspended', 'completed', 'closed', 'archived'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $projectId = $this->input('project_id');

            if (! is_string($projectId)) {
                return;
            }

            $exists = Site::query()
                ->where('project_id', $projectId)
                ->where('reference', mb_strtoupper((string) $this->input('reference')))
                ->exists();

            if ($exists) {
                $validator->errors()->add('reference', 'The site reference has already been taken for this project.');
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'manager_id' => $this->input('manager_id') === '' ? null : $this->input('manager_id'),
            'reference' => mb_strtoupper((string) $this->input('reference')),
        ]);
    }
}
