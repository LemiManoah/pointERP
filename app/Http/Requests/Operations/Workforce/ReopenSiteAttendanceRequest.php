<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Workforce;

use Illuminate\Foundation\Http\FormRequest;

final class ReopenSiteAttendanceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
