<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\QaInspection;

class QaInspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QaInspection $inspection): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QaInspection $inspection): bool
    {
        return true;
    }

    public function delete(User $user, QaInspection $inspection): bool
    {
        return true;
    }
}
