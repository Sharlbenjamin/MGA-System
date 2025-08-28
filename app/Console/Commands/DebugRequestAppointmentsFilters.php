<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use Illuminate\Support\Facades\DB;

class DebugRequestAppointmentsFilters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:request-filters {--country= : Filter by country name} {--service= : Filter by service type name} {--city= : Filter by city name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug request appointments filtering issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $countryName = $this->option('country');
        $serviceName = $this->option('service');
        $cityName = $this->option('city');

        $this->info('🔍 Debugging Request Appointments Filters...');
        $this->newLine();

        // Get all provider branches with relationships
        $query = ProviderBranch::with(['provider', 'provider.country', 'cities', 'branchServices.serviceType']);

        $this->info("📊 Total provider branches: " . $query->count());
        $this->newLine();

        // Debug country filter
        if ($countryName) {
            $this->debugCountryFilter($countryName);
        }

        // Debug service type filter
        if ($serviceName) {
            $this->debugServiceFilter($serviceName);
        }

        // Debug city filter
        if ($cityName) {
            $this->debugCityFilter($cityName);
        }

        // Show all branches with their relationships
        $this->showAllBranchesWithRelationships();

        return 0;
    }

    /**
     * Debug country filter
     */
    private function debugCountryFilter($countryName)
    {
        $this->info("🏳️  Debugging Country Filter: {$countryName}");
        
        $country = Country::where('name', 'LIKE', "%{$countryName}%")->first();
        if (!$country) {
            $this->error("❌ Country '{$countryName}' not found!");
            return;
        }

        $this->line("   📍 Found country: {$country->name} (ID: {$country->id})");

        // Find providers in this country
        $providers = Provider::where('country_id', $country->id)->get();
        $this->line("   🏥 Providers in {$country->name}: {$providers->count()}");

        foreach ($providers as $provider) {
            $branches = $provider->branches;
            $this->line("     • {$provider->name} - Branches: {$branches->count()}");
            foreach ($branches as $branch) {
                $this->line("       - {$branch->branch_name} (ID: {$branch->id})");
            }
        }

        // Check branches with provider country relationship
        $branchesWithCountry = ProviderBranch::whereHas('provider', function($q) use ($country) {
            $q->where('country_id', $country->id);
        })->get();

        $this->line("   🔗 Branches with provider in {$country->name}: {$branchesWithCountry->count()}");
        $this->newLine();
    }

    /**
     * Debug service type filter
     */
    private function debugServiceFilter($serviceName)
    {
        $this->info("🏥 Debugging Service Type Filter: {$serviceName}");
        
        $serviceType = ServiceType::where('name', 'LIKE', "%{$serviceName}%")->first();
        if (!$serviceType) {
            $this->error("❌ Service type '{$serviceName}' not found!");
            return;
        }

        $this->line("   🏥 Found service type: {$serviceType->name} (ID: {$serviceType->id})");

        // Find branches with this service
        $branchesWithService = ProviderBranch::whereHas('branchServices', function($q) use ($serviceType) {
            $q->where('service_type_id', $serviceType->id)->where('is_active', 1);
        })->get();

        $this->line("   🔗 Branches with {$serviceType->name} service: {$branchesWithService->count()}");
        
        foreach ($branchesWithService as $branch) {
            $this->line("     • {$branch->branch_name} (Provider: {$branch->provider->name})");
        }
        $this->newLine();
    }

    /**
     * Debug city filter
     */
    private function debugCityFilter($cityName)
    {
        $this->info("🏙️  Debugging City Filter: {$cityName}");
        
        $city = City::where('name', 'LIKE', "%{$cityName}%")->first();
        if (!$city) {
            $this->error("❌ City '{$cityName}' not found!");
            return;
        }

        $this->line("   🏙️  Found city: {$city->name} (ID: {$city->id})");

        // Find branches with this city
        $branchesWithCity = ProviderBranch::whereHas('cities', function($q) use ($city) {
            $q->where('cities.id', $city->id);
        })->get();

        $this->line("   🔗 Branches in {$city->name}: {$branchesWithCity->count()}");
        
        foreach ($branchesWithCity as $branch) {
            $this->line("     • {$branch->branch_name} (Provider: {$branch->provider->name})");
        }
        $this->newLine();
    }

    /**
     * Show all branches with their relationships
     */
    private function showAllBranchesWithRelationships()
    {
        $this->info("📋 All Branches with Relationships:");
        
        $branches = ProviderBranch::with(['provider.country', 'cities', 'branchServices.serviceType'])->get();
        
        foreach ($branches as $branch) {
            $this->line("🏢 {$branch->branch_name} (ID: {$branch->id})");
            $this->line("   🏥 Provider: {$branch->provider->name}");
            $this->line("   🏳️  Country: {$branch->provider->country->name}");
            
            $cities = $branch->cities ? $branch->cities->pluck('name')->implode(', ') : 'None';
            $this->line("   🏙️  Cities: " . $cities);
            
            $services = $branch->branchServices ? $branch->branchServices->pluck('serviceType.name')->implode(', ') : 'None';
            $this->line("   🏥 Services: " . $services);
            
            $this->line("   📧 Email: " . ($branch->email ?: 'Not set'));
            $this->line("   📞 Phone: " . ($branch->phone ?: 'Not set'));
            $this->line("   📍 Address: " . ($branch->address ?: 'Not set'));
            $this->newLine();
        }
    }
}
