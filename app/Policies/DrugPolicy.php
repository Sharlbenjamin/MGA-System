<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Drug;

class DrugPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Drug');
    }

    public function view(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('view Drug');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Drug');
    }

    public function update(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('edit Drug');
    }

    public function delete(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('delete Drug');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Drug');
    }
}