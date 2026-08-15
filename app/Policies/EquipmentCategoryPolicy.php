<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantAccess;

final class EquipmentCategoryPolicy
{
    use ChecksTenantAccess;

    public function viewAny(User $user): bool { return $user->can('equipment.view') || $user->can('equipment.categories.manage'); }
    public function view(User $user, EquipmentCategory $category): bool { return $this->belongsToSameTenant($user, $category->tenant_id) && $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('equipment.categories.manage'); }
    public function update(User $user, EquipmentCategory $category): bool { return $this->view($user, $category) && $this->create($user); }
    public function delete(User $user, EquipmentCategory $category): bool { return $this->update($user, $category); }
}
