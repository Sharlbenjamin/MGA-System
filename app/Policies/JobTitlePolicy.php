<?php

namespace App\Policies;

use App\Models\JobTitle;
use App\Models\User;

class JobTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function view(User $user, JobTitle $jobTitle): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function create(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function update(User $user, JobTitle $jobTitle): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function delete(User $user, JobTitle $jobTitle): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }
}
