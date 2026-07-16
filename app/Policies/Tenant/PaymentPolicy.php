<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->isSuperAdmin();
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $payment->isCompleted() && ($user->isAdmin() || $user->isSuperAdmin());
    }
}
