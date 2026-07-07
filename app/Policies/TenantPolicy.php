<?php

namespace App\Policies;

use App\Models\Master\User;
use App\Models\Master\Tenant;

class TenantPolicy
{
    /**
     * Determinar se o usuário pode visualizar qualquer tenant
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode visualizar o tenant
     */
    public function view(User $user, Tenant $tenant): bool
    {
        // Super admin vê tudo
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário/Admin da empresa vê seus tenants
        return $user->id === $tenant->company->owner_id ||
               $user->companies()->where('companies.id', $tenant->company_id)->exists();
    }

    /**
     * Determinar se o usuário pode criar tenant
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode atualizar tenant
     */
    public function update(User $user, Tenant $tenant): bool
    {
        // Super admin pode atualizar qualquer coisa
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário da empresa pode atualizar
        return $user->id === $tenant->company->owner_id;
    }

    /**
     * Determinar se o usuário pode deletar tenant
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode restaurar tenant
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar permanentemente
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }
}
