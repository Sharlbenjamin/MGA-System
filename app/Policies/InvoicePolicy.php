<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Invoice');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('view Invoice');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Invoice');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('edit Invoice');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('delete Invoice');
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
