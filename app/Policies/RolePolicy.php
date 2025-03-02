<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * Determine if the user can view a specific role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * Determine if the user can create a new role.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create roles');
    }

    /**
     * Determine if the user can update a role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('edit roles');
    }

    /**
     * Determine if the user can delete a role.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('delete roles');
    }
}