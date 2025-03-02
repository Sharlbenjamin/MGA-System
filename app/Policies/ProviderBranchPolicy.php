<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProviderBranch;

class ProviderBranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view ProviderBranch');
    }

    public function view(User $user, ProviderBranch $providerBranch): bool
    {
        return $user->hasPermissionTo('view ProviderBranch');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create ProviderBranch');
    }

    public function update(User $user, ProviderBranch $providerBranch): bool
    {
        return $user->hasPermissionTo('edit ProviderBranch');
    }

    public function delete(User $user, ProviderBranch $providerBranch): bool
    {
        return $user->hasPermissionTo('delete ProviderBranch');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete ProviderBranch');
    }
}