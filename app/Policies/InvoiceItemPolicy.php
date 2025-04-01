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
        return $user->hasPermissionTo('view invoice items');
    }

    public function view(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('view invoice items');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('create invoice items');
    }

    public function update(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('edit invoice items');
    }

    public function delete(User $user, InvoiceItem $invoiceItem)
    {
        return $user->hasPermissionTo('delete invoice items');
    }
}