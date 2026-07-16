<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\QaTemplate;

class QaTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QaTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QaTemplate $template): bool
    {
        return true;
    }

    public function delete(User $user, QaTemplate $template): bool
    {
        return true;
    }
}
