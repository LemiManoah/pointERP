<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations\Documents;

final class UpdateDocumentRequest extends StoreDocumentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->metadataRules();
    }
}
