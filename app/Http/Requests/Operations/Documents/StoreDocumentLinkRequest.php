<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDocumentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['contract', 'project', 'site', 'daily_site_report', 'equipment', 'equipment_maintenance_work_order', 'inventory_item', 'expense'])],
            'id' => ['required', 'uuid'],
        ];
    }
}
