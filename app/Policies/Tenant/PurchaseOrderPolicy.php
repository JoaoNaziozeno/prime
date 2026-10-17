<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\PurchaseOrder;

class PurchaseOrderPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $this->hasPermission($user, 'purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $this->hasPermission($user, 'purchase_orders.edit');
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $user->isAdmin();
    }
}
