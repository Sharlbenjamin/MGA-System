<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function create(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function deleteAny(User $user): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }

    public function generateContract(User $user, Employee $employee): bool
    {
        return $user->roles?->contains('name', 'admin') ?? false;
    }
}
