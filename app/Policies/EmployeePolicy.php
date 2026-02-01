<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Employee');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('view Employee');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Employee');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('edit Employee');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasPermissionTo('delete Employee');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Employee');
    }
}
