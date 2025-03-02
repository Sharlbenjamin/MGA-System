<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Gop;

class GopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Gop');
    }

    public function view(User $user, Gop $gop): bool
    {
        return $user->hasPermissionTo('view Gop');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Gop');
    }

    public function update(User $user, Gop $gop): bool
    {
        return $user->hasPermissionTo('edit Gop');
    }

    public function delete(User $user, Gop $gop): bool
    {
        return $user->hasPermissionTo('delete Gop');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Gop');
    }
}