<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view CommunicationTemplate',
            'create CommunicationTemplate',
            'edit CommunicationTemplate',
            'delete CommunicationTemplate',
            'contact providers',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();

        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $names = [
            'view CommunicationTemplate',
            'create CommunicationTemplate',
            'edit CommunicationTemplate',
            'delete CommunicationTemplate',
            'contact providers',
        ];

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->delete();
    }
};
