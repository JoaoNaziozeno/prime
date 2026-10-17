<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Document;

class DocumentPolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->hasPermission($user, 'documents.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'documents.create');
    }

    public function download(User $user, Document $document): bool
    {
        return $this->hasPermission($user, 'documents.view');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isAdmin();
    }
}
