<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Invoice;

class InvoicePolicy
{
    private function hasPermission(User $user, string $permission): bool
    {
        return $user->isAdmin() || $user->hasTenantPermission($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.view');
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.edit') && $invoice->isDraft();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && ($invoice->isDraft() || $invoice->isCancelled());
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->isSuperAdmin();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.send') && $invoice->canSend();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->hasPermission($user, 'invoices.payment') && $invoice->canRecordPayment();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->canCancel();
    }
}
