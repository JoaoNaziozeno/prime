<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Service;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Service $service): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Service $service): bool
    {
        return true;
    }

    public function delete(User $user, Service $service): bool
    {
        return true;
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $user->isSuperAdmin();
    }
}
