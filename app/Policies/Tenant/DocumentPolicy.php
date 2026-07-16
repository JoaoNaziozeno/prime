<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Document;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function download(User $user, Document $document): bool
    {
        return true;
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }
}
