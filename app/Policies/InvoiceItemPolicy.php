<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InvoiceItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoiceItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('view InvoiceItem');
    }

    public function view(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('view InvoiceItem');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('create InvoiceItem');
    }

    public function update(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('edit InvoiceItem');
    }

    public function delete(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('delete InvoiceItem');
    }
}