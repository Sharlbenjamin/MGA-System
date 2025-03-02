<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Lead;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Lead');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermissionTo('view Lead');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Lead');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermissionTo('edit Lead');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermissionTo('delete Lead');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Lead');
    }
}