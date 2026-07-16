<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\OrderProductLine;

class OrderProductLinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderProductLine $line): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, OrderProductLine $line): bool
    {
        // Can only update if the associated order of service is editable (draft/approved/in_progress)
        $order = $line->order;
        return $order ? ($order->isDraft() || $order->isApproved() || $order->isInProgress()) : true;
    }

    public function delete(User $user, OrderProductLine $line): bool
    {
        // Can only delete if the associated order is editable
        $order = $line->order;
        return $order ? ($order->isDraft() || $order->isApproved() || $order->isInProgress()) : true;
    }

    public function restore(User $user, OrderProductLine $line): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, OrderProductLine $line): bool
    {
        return $user->isSuperAdmin();
    }
}
