<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Estimates;

use App\Models\ProjectEstimate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class PreviewBoqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [ProjectEstimate::class, $this->route('project')]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'target_id' => ['nullable', 'uuid'],
            'sheets' => ['required', 'array', 'min:1', 'max:100'],
            'sheets.*.sheet' => ['required', 'string', 'distinct'],
            'sheets.*.start_row' => ['required', 'integer', 'min:1', 'max:1048576'],
            'sheets.*.end_row' => ['required', 'integer', 'gte:sheets.*.start_row', 'max:1048576'],
            'sheets.*.bill' => ['nullable', 'string', 'max:160'],
            'sheets.*.section' => ['nullable', 'string', 'max:160'],
            'sheets.*.element' => ['nullable', 'string', 'max:160'],
            'sheets.*.reference' => ['nullable', 'regex:/^[A-Z]{1,3}$/'],
            'sheets.*.description' => ['required', 'regex:/^[A-Z]{1,3}$/'],
            'sheets.*.unit' => ['required', 'regex:/^[A-Z]{1,3}$/'],
            'sheets.*.quantity' => ['required', 'regex:/^[A-Z]{1,3}$/'],
            'sheets.*.rate' => ['nullable', 'regex:/^[A-Z]{1,3}$/'],
            'sheets.*.amount' => ['nullable', 'regex:/^[A-Z]{1,3}$/'],
        ];
    }
}
