<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\CustomReport;

class CustomReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CustomReport $report): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CustomReport $report): bool
    {
        return true;
    }

    public function delete(User $user, CustomReport $report): bool
    {
        return true;
    }
}
