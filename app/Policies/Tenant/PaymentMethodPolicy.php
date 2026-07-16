<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\PaymentMethod;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
