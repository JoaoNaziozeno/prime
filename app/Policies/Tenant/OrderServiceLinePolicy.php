<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\OrderServiceLine;

class OrderServiceLinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderServiceLine $line): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, OrderServiceLine $line): bool
    {
        // Can only update if the associated order of service is editable (draft/approved/in_progress)
        $order = $line->order;
        return $order ? ($order->isDraft() || $order->isApproved() || $order->isInProgress()) : true;
    }

    public function delete(User $user, OrderServiceLine $line): bool
    {
        // Can only delete if the associated order is editable
        $order = $line->order;
        return $order ? ($order->isDraft() || $order->isApproved() || $order->isInProgress()) : true;
    }

    public function restore(User $user, OrderServiceLine $line): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, OrderServiceLine $line): bool
    {
        return $user->isSuperAdmin();
    }

    public function start(User $user, OrderServiceLine $line): bool
    {
        return $line->canStart();
    }

    public function complete(User $user, OrderServiceLine $line): bool
    {
        return $line->canComplete();
    }

    public function cancel(User $user, OrderServiceLine $line): bool
    {
        return $line->canCancel();
    }
}
