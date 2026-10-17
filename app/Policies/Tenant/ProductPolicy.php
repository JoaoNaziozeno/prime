<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Product;

class ProductPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->hasPermission($user, 'products.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->hasPermission($user, 'products.edit');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->isSuperAdmin();
    }
}
