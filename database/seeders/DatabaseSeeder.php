<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DraftMailSeeder::class,
            CountriesTableSeeder::class,
            PrimaryCitiesSeeder::class,
            ClientSeeder::class,
            LeadSeeder::class,
            ProvidersSeeder::class,
            ServiceTypesSeeder::class,
            ProviderLeadsSeeder::class,
            UsersSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            ProviderDraftMailSeeder::class,
            ProviderBranchSeeder::class,
            ProvinceSeeder::class,
        ]);
    }
}
