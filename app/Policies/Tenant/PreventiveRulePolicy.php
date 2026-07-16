<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\PreventiveRule;

class PreventiveRulePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PreventiveRule $rule): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PreventiveRule $rule): bool
    {
        return true;
    }

    public function delete(User $user, PreventiveRule $rule): bool
    {
        return true;
    }
}
