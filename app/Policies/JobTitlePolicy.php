<?php

namespace App\Policies;

use App\Models\JobTitle;
use App\Models\User;

class JobTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view JobTitle');
    }

    public function view(User $user, JobTitle $jobTitle): bool
    {
        return $user->hasPermissionTo('view JobTitle');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create JobTitle');
    }

    public function update(User $user, JobTitle $jobTitle): bool
    {
        return $user->hasPermissionTo('edit JobTitle');
    }

    public function delete(User $user, JobTitle $jobTitle): bool
    {
        return $user->hasPermissionTo('delete JobTitle');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete JobTitle');
    }
}
