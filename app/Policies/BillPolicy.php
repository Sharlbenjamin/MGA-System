<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Bill');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('view Bill');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Bill');
    }

    public function update(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('edit Bill');
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $user->hasPermissionTo('delete Bill');
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
