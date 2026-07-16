<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\MaintenanceLog;

class MaintenanceLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceLog $log): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MaintenanceLog $log): bool
    {
        return true;
    }

    public function delete(User $user, MaintenanceLog $log): bool
    {
        return true;
    }
}
