<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Team;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Team');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('view Team');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Team');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('edit Team');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('delete Team');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Team');
    }
}