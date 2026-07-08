<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\OrderItem;

class OrderItemPolicy
{
    /**
     * Determine if the user can view any items
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the item
     */
    public function view(User $user, OrderItem $item): bool
    {
        return true;
    }

    /**
     * Determine if the user can create items
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the item
     */
    public function update(User $user, OrderItem $item): bool
    {
        // Can update pending and blocked items
        return $item->isPending() || $item->isBlocked();
    }

    /**
     * Determine if the user can delete the item
     */
    public function delete(User $user, OrderItem $item): bool
    {
        // Can only delete pending or cancelled items
        return $item->isPending() || $item->isCancelled();
    }

    /**
     * Determine if the user can restore the item
     */
    public function restore(User $user, OrderItem $item): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can permanently delete the item
     */
    public function forceDelete(User $user, OrderItem $item): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can start the item
     */
    public function start(User $user, OrderItem $item): bool
    {
        return $item->canStart();
    }

    /**
     * Determine if the user can complete the item
     */
    public function complete(User $user, OrderItem $item): bool
    {
        return $item->canComplete();
    }

    /**
     * Determine if the user can cancel the item
     */
    public function cancel(User $user, OrderItem $item): bool
    {
        return $item->canCancel();
    }

    /**
     * Determine if the user can block the item
     */
    public function block(User $user, OrderItem $item): bool
    {
        return !$item->isCompleted() && !$item->isCancelled();
    }

    /**
     * Determine if the user can unblock the item
     */
    public function unblock(User $user, OrderItem $item): bool
    {
        return $item->isBlocked();
    }
}
