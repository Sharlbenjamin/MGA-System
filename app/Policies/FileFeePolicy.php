<?php

namespace App\Policies;

use App\Models\FileFee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FileFeePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view FileFee');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FileFee $fileFee): bool
    {
        return $user->can('view FileFee');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create FileFee');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FileFee $fileFee): bool
    {
        return $user->can('edit FileFee');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FileFee $fileFee): bool
    {
        return $user->can('delete FileFee');
    }
}