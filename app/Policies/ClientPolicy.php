<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view Client');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasPermissionTo('view Client');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create Client');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermissionTo('edit Client');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermissionTo('delete Client');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete Client');
    }
}