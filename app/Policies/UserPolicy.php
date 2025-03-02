<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view User');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('view User');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create User');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('edit User');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('delete User');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete User');
    }
}