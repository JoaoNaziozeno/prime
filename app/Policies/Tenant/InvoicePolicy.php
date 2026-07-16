<?php

namespace App\Policies\Tenant;

use App\Models\Master\User;
use App\Models\Tenant\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() || $invoice->isCancelled();
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
        return $invoice->canSend();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $invoice->canRecordPayment();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $invoice->canCancel();
    }
}
