<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\InventoryLog;

class InventoryLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InventoryLog $log): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InventoryLog $log): bool
    {
        return false; // Logs de movimentações são imutáveis por padrão
    }

    public function delete(User $user, InventoryLog $log): bool
    {
        return false; // Logs de movimentações são imutáveis por padrão
    }
}
