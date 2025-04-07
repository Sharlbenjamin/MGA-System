<?php

namespace App\Policies;

use App\Models\BillItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillItemPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view BillItem');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BillItem $billItem): bool
    {
        return $user->can('view BillItem');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create BillItem');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BillItem $billItem): bool
    {
        return $user->can('update BillItem');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BillItem $billItem): bool
    {
        return $user->can('delete BillItem');
    }
}