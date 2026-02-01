<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function create(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }
}
