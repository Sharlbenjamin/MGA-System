<?php

namespace App\Policies;

use App\Models\CommunicationTemplate;
use App\Models\User;

class CommunicationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view CommunicationTemplate');
    }

    public function view(User $user, CommunicationTemplate $communicationTemplate): bool
    {
        return $user->hasPermissionTo('view CommunicationTemplate');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create CommunicationTemplate');
    }

    public function update(User $user, CommunicationTemplate $communicationTemplate): bool
    {
        return $user->hasPermissionTo('edit CommunicationTemplate');
    }

    public function delete(User $user, CommunicationTemplate $communicationTemplate): bool
    {
        return $user->hasPermissionTo('delete CommunicationTemplate');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete CommunicationTemplate');
    }
}
