<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\ServiceCategory;

class ServiceCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ServiceCategory $category): bool
    {
        return true;
    }

    public function delete(User $user, ServiceCategory $category): bool
    {
        return true;
    }

    public function restore(User $user, ServiceCategory $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, ServiceCategory $category): bool
    {
        return $user->isSuperAdmin();
    }
}
