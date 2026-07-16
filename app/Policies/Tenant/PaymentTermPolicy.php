<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\PaymentTerm;

class PaymentTermPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PaymentTerm $term): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function update(User $user, PaymentTerm $term): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, PaymentTerm $term): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
