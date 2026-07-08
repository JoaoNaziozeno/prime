<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return true;
    }

    public function delete(User $user, Product $product): bool
    {
        return true;
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
