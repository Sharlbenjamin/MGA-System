<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $resources = [
            'Client',
            'Country',
            'User',
            'City',
            'Contact',
            'DraftMail',
            'Lead',
            'ProviderBranch',
            'ProviderLead',
            'Provider',
            'Patient',
            'File',
            'MedicalReport',
            'Gop',
            'Prescription',
            'Drug',
        ];

        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$resource}",
                    'guard_name' => 'web'
                ]);
            }
        }

        $this->command->info('Permissions seeded successfully.');
    }
}