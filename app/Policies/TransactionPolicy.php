<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Transaction');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('view Transaction');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Transaction');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('edit Transaction');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->hasPermissionTo('delete Transaction');
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
