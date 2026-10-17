<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Customer;

class CustomerPolicy
{
    /**
     * Admins always pass. Regular users need the explicit permission.
     */
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->hasPermission($user, 'customers.edit');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->isSuperAdmin();
    }
}
