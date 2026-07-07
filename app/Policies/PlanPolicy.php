<?php

namespace App\Policies;

use App\Models\Master\User;
use App\Models\Master\Plan;

class PlanPolicy
{
    /**
     * Determinar se o usuário pode visualizar qualquer plano
     */
    public function viewAny(User $user): bool
    {
        return true; // Qualquer usuário pode ver planos disponíveis
    }

    /**
     * Determinar se o usuário pode visualizar o plano
     */
    public function view(User $user, Plan $plan): bool
    {
        return true; // Qualquer usuário pode ver um plano
    }

    /**
     * Determinar se o usuário pode criar plano
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode atualizar plano
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar plano
     */
    public function delete(User $user, Plan $plan): bool
    {
        // Não deixar deletar se há assinaturas ativas
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode restaurar plano
     */
    public function restore(User $user, Plan $plan): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar permanentemente
     */
    public function forceDelete(User $user, Plan $plan): bool
    {
        return $user->isSuperAdmin();
    }
}
