<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use App\Models\ProjectEstimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UploadBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [ProjectEstimate::class, $this->route('project')]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:15360']];
    }
}
