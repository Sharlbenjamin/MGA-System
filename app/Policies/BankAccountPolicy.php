<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BankAccountPolicy
{

    public function viewAny(User $user): bool
    {
        return $user->can('view-any BankAccount');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('view BankAccount');
    }

    public function create(User $user): bool
    {
        return $user->can('create BankAccount');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('edit BankAccount');
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('delete BankAccount');
    }

    public function restore(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('restore BankAccount');
    }

    public function forceDelete(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('force-delete BankAccount');
    }
}
