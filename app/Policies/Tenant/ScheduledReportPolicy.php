<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\ScheduledReport;

class ScheduledReportPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'reports.view');
    }

    public function view(User $user, ScheduledReport $report): bool
    {
        return $this->hasPermission($user, 'reports.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'reports.create');
    }

    public function update(User $user, ScheduledReport $report): bool
    {
        return $this->hasPermission($user, 'reports.edit');
    }

    public function delete(User $user, ScheduledReport $report): bool
    {
        return $user->isAdmin();
    }
}
