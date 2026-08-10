<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class DocumentPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool
    {
        if ($user->can('documents.view')) {
            return true;
        }
        if ($user->can('documents.upload')) {
            return true;
        }

        return $user->can('documents.update');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->belongsToSameTenant($user, $document->tenant_id)
            && $this->canAccessBranch($user, $document->branch_id)
            && $user->can('documents.view')
            && $this->canViewConfidentiality($user, $document)
            && $this->canAccessLinks($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $user->can('documents.download') && $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function update(User $user, Document $document): bool
    {
        return $document->status !== Document::STATUS_ARCHIVED
            && $user->can('documents.update')
            && $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.archive')
            && $this->belongsToSameTenant($user, $document->tenant_id)
            && $this->canAccessBranch($user, $document->branch_id);
    }

    public function version(User $user, Document $document): bool
    {
        return $user->can('documents.version') && $this->update($user, $document);
    }

    public function link(User $user, Document $document): bool
    {
        return $user->can('documents.link') && $this->update($user, $document);
    }

    public function unlink(User $user, Document $document): bool
    {
        return $user->can('documents.unlink') && $this->update($user, $document);
    }

    private function canViewConfidentiality(User $user, Document $document): bool
    {
        if (! $document->isConfidential()) {
            return true;
        }

        return $user->can('documents.view-confidential');
    }

    private function canAccessLinks(User $user, Document $document): bool
    {
        $document->loadMissing('links.linkable');

        if ($document->links->isEmpty()) {
            return true;
        }

        return $document->links->contains(function ($link) use ($user): bool {
            $target = $link->linkable;

            return $target instanceof Model
                && Gate::forUser($user)->allows('view', $target);
        });
    }
}
