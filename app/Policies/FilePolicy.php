<?php
namespace App\Policies;

use App\Models\User;
use App\Models\File;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view File');
    }

    public function view(User $user, File $file): bool
    {
        return $user->hasPermissionTo('view File');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create File');
    }

    public function update(User $user, File $file): bool
    {
        return $user->hasPermissionTo('edit File');
    }

    public function delete(User $user, File $file): bool
    {
        return $user->hasPermissionTo('delete File');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete File');
    }
}