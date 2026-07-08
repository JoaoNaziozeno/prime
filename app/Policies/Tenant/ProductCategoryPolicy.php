<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\ProductCategory;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return true;
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return true;
    }

    public function restore(User $user, ProductCategory $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, ProductCategory $category): bool
    {
        return $user->isSuperAdmin();
    }
}
