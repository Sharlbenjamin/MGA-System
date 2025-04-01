<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Invoices');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('view Invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Invoices');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('edit Invoices');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('delete Invoices');
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
