<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PriceList;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view PriceList');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('view PriceList');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create PriceList');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('edit PriceList');
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->hasPermissionTo('delete PriceList');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete PriceList');
    }
} 