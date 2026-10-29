<?php

namespace App\Support;

use App\Models\User;

class MobileUserPayload
{
    public static function from(User $user): array
    {
        $user->loadMissing(['roles', 'signature']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'is_admin' => $user->getRoleNames()->contains(fn ($name) => strcasecmp((string) $name, 'admin') === 0),
            'signature' => $user->signature ? [
                'name' => $user->signature->name,
                'job_title' => $user->signature->job_title,
                'department' => $user->signature->department,
                'work_phone' => $user->signature->work_phone,
            ] : null,
        ];
    }
}
