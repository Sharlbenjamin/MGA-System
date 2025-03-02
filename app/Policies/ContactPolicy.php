<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Contact;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Contact');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermissionTo('view Contact');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Contact');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermissionTo('edit Contact');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermissionTo('delete Contact');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Contact');
    }
}