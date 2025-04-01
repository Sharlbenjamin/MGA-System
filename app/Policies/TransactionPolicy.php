<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Transactions');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('view Transactions');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Transactions');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('edit Transactions');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('delete Transactions');
    }

    public function restore(User $user, Transaction $transaction): bool
    {
        return false;
    }

    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return false;
    }
}
