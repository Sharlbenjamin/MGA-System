<?php

namespace App\Policies;

use App\Models\User;
use App\Models\City;

class CityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view City');
    }

    public function view(User $user, City $city): bool
    {
        return $user->hasPermissionTo('view City');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create City');
    }

    public function update(User $user, City $city): bool
    {
        return $user->hasPermissionTo('edit City');
    }

    public function delete(User $user, City $city): bool
    {
        return $user->hasPermissionTo('delete City');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete City');
    }
}