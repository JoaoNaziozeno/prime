<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Schedule;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return true;
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
