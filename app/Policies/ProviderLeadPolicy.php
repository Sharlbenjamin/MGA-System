<?php
namespace App\Policies;

use App\Models\User;
use App\Models\ProviderLead;

class ProviderLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view ProviderLead');
    }

    public function view(User $user, ProviderLead $providerLead): bool
    {
        return $user->hasPermissionTo('view ProviderLead');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create ProviderLead');
    }

    public function update(User $user, ProviderLead $providerLead): bool
    {
        return $user->hasPermissionTo('edit ProviderLead');
    }

    public function delete(User $user, ProviderLead $providerLead): bool
    {
        return $user->hasPermissionTo('delete ProviderLead');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete ProviderLead');
    }
}