<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any permissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * Determine if the user can view a specific permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * Determine if the user can create a new permission.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create permissions');
    }

    /**
     * Determine if the user can update a permission.
     */
    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('edit permissions');
    }

    /**
     * Determine if the user can delete a permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('delete permissions');
    }
}