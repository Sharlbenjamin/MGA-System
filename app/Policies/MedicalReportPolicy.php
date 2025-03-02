<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MedicalReport;

class MedicalReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view MedicalReport');
    }

    public function view(User $user, MedicalReport $medicalReport): bool
    {
        return $user->hasPermissionTo('view MedicalReport');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create MedicalReport');
    }

    public function update(User $user, MedicalReport $medicalReport): bool
    {
        return $user->hasPermissionTo('edit MedicalReport');
    }

    public function delete(User $user, MedicalReport $medicalReport): bool
    {
        return $user->hasPermissionTo('delete MedicalReport');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete MedicalReport');
    }
}