<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchContext;

final readonly class SelectCurrentBranch
{
    public function __construct(private BranchContext $branchContext)
    {
        //
    }

    public function handle(?string $branchId, User $user): ?Branch
    {
        return $this->branchContext->select($branchId, $user);
    }
}
