<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\OrderOfService;

class OrderOfServicePolicy
{
    /**
     * Determine if the user can view any orders
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the order
     */
    public function view(User $user, OrderOfService $order): bool
    {
        return true;
    }

    /**
     * Determine if the user can create orders
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the order
     */
    public function update(User $user, OrderOfService $order): bool
    {
        // Can only update draft or approved orders
        return $order->isDraft() || $order->isApproved();
    }

    /**
     * Determine if the user can delete the order
     */
    public function delete(User $user, OrderOfService $order): bool
    {
        // Can only delete draft or cancelled orders
        return $order->isDraft() || $order->isCancelled();
    }

    /**
     * Determine if the user can restore the order
     */
    public function restore(User $user, OrderOfService $order): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can permanently delete the order
     */
    public function forceDelete(User $user, OrderOfService $order): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can approve the order
     */
    public function approve(User $user, OrderOfService $order): bool
    {
        return $order->canApprove();
    }

    /**
     * Determine if the user can start the order
     */
    public function start(User $user, OrderOfService $order): bool
    {
        return $order->canStart();
    }

    /**
     * Determine if the user can complete the order
     */
    public function complete(User $user, OrderOfService $order): bool
    {
        return $order->canComplete();
    }

    /**
     * Determine if the user can cancel the order
     */
    public function cancel(User $user, OrderOfService $order): bool
    {
        return $order->canCancel();
    }

    /**
     * Determine if the user can hold the order
     */
    public function hold(User $user, OrderOfService $order): bool
    {
        return $order->isInProgress();
    }

    /**
     * Determine if the user can resume the order
     */
    public function resume(User $user, OrderOfService $order): bool
    {
        return $order->isOnHold();
    }
}
