<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email_enabled' => ['required', 'boolean'],
            'muted_email_categories' => ['array'],
            'muted_email_categories.*' => ['string', Rule::in([
                'dsr_assignment',
                'dsr_expected',
                'dsr_submitted',
                'dsr_returned',
                'dsr_approved',
                'dsr_correction',
                'dsr_missing',
                'dsr_escalation',
                'document_expiry',
                'approval_pending',
            ])],
            'digest_frequency' => ['required', Rule::in(['immediate', 'daily', 'weekly'])],
        ];
    }
}
