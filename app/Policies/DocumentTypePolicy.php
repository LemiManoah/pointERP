<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentType;
use App\Models\User;

final class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->can('documents.view')) {
            return true;
        }

        return $user->can('documents.manage-types');
    }

    public function view(User $user, DocumentType $documentType): bool
    {
        return $this->viewAny($user)
            && ($documentType->tenant_id === null || $documentType->tenant_id === $user->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage-types');
    }

    public function update(User $user, DocumentType $documentType): bool
    {
        return $user->can('documents.manage-types')
            && ($documentType->tenant_id === null || $documentType->tenant_id === $user->tenant_id);
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return $this->update($user, $documentType);
    }
}
