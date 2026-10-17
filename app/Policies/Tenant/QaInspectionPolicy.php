<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\QaInspection;

class QaInspectionPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'qa.view');
    }

    public function view(User $user, QaInspection $inspection): bool
    {
        return $this->hasPermission($user, 'qa.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'qa.create');
    }

    public function update(User $user, QaInspection $inspection): bool
    {
        return $this->hasPermission($user, 'qa.edit');
    }

    public function delete(User $user, QaInspection $inspection): bool
    {
        return $user->isAdmin();
    }
}
