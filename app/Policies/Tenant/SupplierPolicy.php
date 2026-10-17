<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Supplier;

class SupplierPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->hasPermission($user, 'suppliers.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->hasPermission($user, 'suppliers.edit');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->isAdmin();
    }
}
