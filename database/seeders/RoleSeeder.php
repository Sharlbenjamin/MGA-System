<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Create Admin Role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Assign all permissions to admin
        $permissions = Permission::all();
        $adminRole->syncPermissions($permissions);

        // Assign admin role to user with ID 1
        $adminUser = User::find(1);
        if ($adminUser) {
            $adminUser->assignRole('admin');
            $this->command->info('User with ID 1 has been assigned the admin role.');
        } else {
            $this->command->warn('User with ID 1 not found.');
        }
    }
}