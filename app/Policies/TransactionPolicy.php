<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    protected function canViewAllTransactionTypes(User $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
            'admin',
            'Admin',
            'financial',
            'Financial',
            'financial manager',
            'Financial Manager',
            'manager',
            'Manager',
        ]);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Transaction');
    }

    public function view(User $user, Transaction $transaction): bool
    {
        if (!$user->hasPermissionTo('view Transaction')) {
            return false;
        }

        return $this->canViewAllTransactionTypes($user) || $transaction->type === 'Outflow';
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Transaction');
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if (!$user->hasPermissionTo('edit Transaction')) {
            return false;
        }

        return $this->canViewAllTransactionTypes($user) || $transaction->type === 'Outflow';
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        if (!$user->hasPermissionTo('delete Transaction')) {
            return false;
        }

        return $this->canViewAllTransactionTypes($user) || $transaction->type === 'Outflow';
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
