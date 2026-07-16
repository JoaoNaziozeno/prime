<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;

class ReportPolicy
{
    public function view(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
