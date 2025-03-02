<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DraftMail;

class DraftMailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view DraftMail');
    }

    public function view(User $user, DraftMail $draftMail): bool
    {
        return $user->hasPermissionTo('view DraftMail');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create DraftMail');
    }

    public function update(User $user, DraftMail $draftMail): bool
    {
        return $user->hasPermissionTo('edit DraftMail');
    }

    public function delete(User $user, DraftMail $draftMail): bool
    {
        return $user->hasPermissionTo('delete DraftMail');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermissionTo('delete DraftMail');
    }
}