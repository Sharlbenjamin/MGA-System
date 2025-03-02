<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PrescriptionPolicy
{
    
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Prescription');
    }

    public function view(User $user, Prescription $gop): bool
    {
        return $user->hasPermissionTo('view Prescription');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Prescription');
    }

    public function update(User $user, Prescription $gop): bool
    {
        return $user->hasPermissionTo('edit Prescription');
    }

    public function delete(User $user, Prescription $gop): bool
    {
        return $user->hasPermissionTo('delete Prescription');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Prescription');
    }

}
