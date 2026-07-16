<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\ScheduledReport;

class ScheduledReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduledReport $report): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ScheduledReport $report): bool
    {
        return true;
    }

    public function delete(User $user, ScheduledReport $report): bool
    {
        return true;
    }
}
