<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Bills');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('view Bills');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Bills');
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('edit Bills');
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('delete Bills');
    }

    public function restore(User $user, Bill $bill): bool
    {
        return false;
    }

    public function forceDelete(User $user, Bill $bill): bool
    {
        return false;
    }
}
