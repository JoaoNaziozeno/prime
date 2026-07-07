<?php

namespace App\Policies;

use App\Models\Master\User;

class UserPolicy
{
    /**
     * Determinar se o usuário pode visualizar qualquer usuário
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode visualizar o usuário
     */
    public function view(User $user, User $target): bool
    {
        // Super admin vê tudo
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Usuário vê a si mesmo
        return $user->id === $target->id;
    }

    /**
     * Determinar se o usuário pode criar usuário
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode atualizar usuário
     */
    public function update(User $user, User $target): bool
    {
        // Super admin pode atualizar qualquer um
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Usuário pode atualizar a si mesmo
        return $user->id === $target->id;
    }

    /**
     * Determinar se o usuário pode deletar usuário
     */
    public function delete(User $user, User $target): bool
    {
        // Apenas super admin pode deletar
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }

    /**
     * Determinar se o usuário pode restaurar usuário
     */
    public function restore(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar permanentemente usuário
     */
    public function forceDelete(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }
}
