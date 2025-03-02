<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Provider;

class ProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Provider');
    }

    public function view(User $user, Provider $provider): bool
    {
        return $user->hasPermissionTo('view Provider');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Provider');
    }

    public function update(User $user, Provider $provider): bool
    {
        return $user->hasPermissionTo('edit Provider');
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $user->hasPermissionTo('delete Provider');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Provider');
    }
}