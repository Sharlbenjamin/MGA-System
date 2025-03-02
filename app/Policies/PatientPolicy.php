<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Patient;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Patient');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->hasPermissionTo('view Patient');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Patient');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermissionTo('edit Patient');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasPermissionTo('delete Patient');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Patient');
    }
}