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
            'FileFee',
            'BankAccount',
            'Invoice',
            'InvoiceItem',
            'Bill',
            'BillItem',
            'Transaction',
            'PriceList',
            'Employee',
            'JobTitle',
            'CommunicationTemplate',
        ];

        $actions = ['view', 'create', 'edit', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }

        Permission::firstOrCreate([
            'name' => 'contact providers',
            'guard_name' => 'web',
        ]);

        $sellingCostPermission = Permission::firstOrCreate([
            'name' => 'edit Gop selling cost',
            'guard_name' => 'web',
        ]);

        $adminRole = \Spatie\Permission\Models\Role::query()
            ->whereIn('name', ['admin', 'Admin', 'super-admin', 'Super Admin'])
            ->get();

        foreach ($adminRole as $role) {
            $role->givePermissionTo($sellingCostPermission);
        }

        $this->command->info('Permissions seeded successfully.');
    }
}
