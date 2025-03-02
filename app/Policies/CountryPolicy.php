<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Country;

class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Country');
    }

    public function view(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('view Country');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Country');
    }

    public function update(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('edit Country');
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->hasPermissionTo('delete Country');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Country');
    }
}