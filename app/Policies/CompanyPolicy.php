<?php

namespace App\Policies;

use App\Models\Master\User;
use App\Models\Master\Company;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * Determinar se o usuário pode visualizar qualquer empresa
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar se o usuário pode visualizar a empresa
     */
    public function view(User $user, Company $company): bool
    {
        // Super admin vê tudo
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário vê sua empresa
        if ($user->id === $company->owner_id) {
            return true;
        }

        // Usuário associado vê a empresa
        return $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determinar se o usuário pode criar empresa
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determinar se o usuário pode atualizar empresa
     */
    public function update(User $user, Company $company): bool
    {
        // Super admin pode atualizar qualquer coisa
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário pode atualizar sua empresa
        return $user->id === $company->owner_id;
    }

    /**
     * Determinar se o usuário pode deletar empresa
     */
    public function delete(User $user, Company $company): bool
    {
        // Apenas super admin pode deletar
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode restaurar empresa
     */
    public function restore(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar permanentemente empresa
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }
}
