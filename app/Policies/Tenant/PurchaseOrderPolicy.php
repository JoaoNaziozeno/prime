<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return true;
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return true;
    }
}
