<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\WarehouseLocation;

class WarehouseLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WarehouseLocation $location): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, WarehouseLocation $location): bool
    {
        return true;
    }

    public function delete(User $user, WarehouseLocation $location): bool
    {
        return true;
    }

    public function restore(User $user, WarehouseLocation $location): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, WarehouseLocation $location): bool
    {
        return $user->isSuperAdmin();
    }
}
