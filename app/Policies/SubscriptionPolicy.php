<?php

namespace App\Policies;

use App\Models\Master\User;
use App\Models\Master\Subscription;

class SubscriptionPolicy
{
    /**
     * Determinar se o usuário pode visualizar qualquer assinatura
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode visualizar a assinatura
     */
    public function view(User $user, Subscription $subscription): bool
    {
        // Super admin vê tudo
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário/Admin da empresa vê suas assinaturas
        return $user->id === $subscription->company->owner_id ||
               $user->companies()->where('companies.id', $subscription->company_id)->exists();
    }

    /**
     * Determinar se o usuário pode criar assinatura
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode atualizar assinatura
     */
    public function update(User $user, Subscription $subscription): bool
    {
        // Super admin pode atualizar qualquer coisa
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Proprietário da empresa pode atualizar
        return $user->id === $subscription->company->owner_id;
    }

    /**
     * Determinar se o usuário pode deletar assinatura
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode restaurar assinatura
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determinar se o usuário pode deletar permanentemente
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return $user->isSuperAdmin();
    }
}
