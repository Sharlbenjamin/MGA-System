<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\BranchService;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\City;
use App\Models\Country;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Builder;

class DebugRequestAppointmentsData extends Command
{
    protected $signature = 'debug:request-appointments {file_id}';
    protected $description = 'Debug RequestAppointments data for a specific file';

    public function handle()
    {
        $fileId = $this->argument('file_id');
        
        $this->info("=== DEBUGGING REQUEST APPOINTMENTS FOR FILE {$fileId} ===");
        
        // Load file
        $file = File::with(['patient', 'serviceType', 'city', 'country'])->find($fileId);
        
        if (!$file) {
            $this->error("File {$fileId} not found!");
            return;
        }
        
        $this->info("\n📄 FILE DETAILS:");
        $this->line("ID: {$file->id}");
        $this->line("Patient: " . ($file->patient->name ?? 'N/A'));
        $this->line("Service Type: " . ($file->serviceType->name ?? 'N/A') . " (ID: {$file->service_type_id})");
        $this->line("City: " . ($file->city->name ?? 'N/A') . " (ID: {$file->city_id})");
        $this->line("Country: " . ($file->country->name ?? 'N/A') . " (ID: {$file->country_id})");
        
        // Test the base query
        $this->info("\n🔍 TESTING BASE QUERY:");
        $baseQuery = BranchService::with([
            'providerBranch.provider.country',
            'providerBranch.operationContact',
            'providerBranch.gopContact', 
            'providerBranch.financialContact',
            'providerBranch.cities',
            'providerBranch.branchCities',
            'serviceType'
        ])
        ->join('provider_branches', 'branch_services.provider_branch_id', '=', 'provider_branches.id')
        ->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
        ->where('branch_services.is_active', 1);
        
        $baseResults = $baseQuery->get();
        $this->line("Base query results: " . $baseResults->count() . " branch services");
        
        // Test service type filter
        $this->info("\n🏥 TESTING SERVICE TYPE FILTER:");
        $serviceQuery = clone $baseQuery;
        $serviceQuery->where('branch_services.service_type_id', $file->service_type_id);
        $serviceResults = $serviceQuery->get();
        $this->line("Service type filter results: " . $serviceResults->count() . " branch services");
        
        foreach ($serviceResults as $result) {
            $this->line("  - {$result->providerBranch->branch_name} (Provider: {$result->providerBranch->provider->name})");
        }
        
        // Test country filter
        $this->info("\n🏳️ TESTING COUNTRY FILTER:");
        $countryQuery = clone $baseQuery;
        $countryQuery->where('providers.country_id', $file->country_id);
        $countryResults = $countryQuery->get();
        $this->line("Country filter results: " . $countryResults->count() . " branch services");
        
        foreach ($countryResults as $result) {
            $this->line("  - {$result->providerBranch->branch_name} (Provider: {$result->providerBranch->provider->name})");
        }
        
        // Test city filter
        $this->info("\n🏙️ TESTING CITY FILTER:");
        $cityQuery = clone $baseQuery;
        $cityQuery->whereExists(function($subQuery) use ($file) {
            $subQuery->select(\DB::raw(1))
                ->from('branch_cities')
                ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                ->where('branch_cities.city_id', $file->city_id);
        });
        $cityResults = $cityQuery->get();
        $this->line("City filter results: " . $cityResults->count() . " branch services");
        
        foreach ($cityResults as $result) {
            $this->line("  - {$result->providerBranch->branch_name} (Provider: {$result->providerBranch->provider->name})");
        }
        
        // Test combined filters
        $this->info("\n🎯 TESTING COMBINED FILTERS:");
        $combinedQuery = clone $baseQuery;
        $combinedQuery->where('branch_services.service_type_id', $file->service_type_id)
            ->where('providers.country_id', $file->country_id)
            ->whereExists(function($subQuery) use ($file) {
                $subQuery->select(\DB::raw(1))
                    ->from('branch_cities')
                    ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                    ->where('branch_cities.city_id', $file->city_id);
            });
        $combinedResults = $combinedQuery->get();
        $this->line("Combined filters results: " . $combinedResults->count() . " branch services");
        
        foreach ($combinedResults as $result) {
            $this->line("  - {$result->providerBranch->branch_name} (Provider: {$result->providerBranch->provider->name})");
        }
        
        // Check what cities are available for the file's country
        $this->info("\n🏙️ CITIES IN FILE'S COUNTRY:");
        $citiesInCountry = City::where('country_id', $file->country_id)->get();
        $this->line("Cities in {$file->country->name}: " . $citiesInCountry->count());
        
        foreach ($citiesInCountry as $city) {
            $this->line("  - {$city->name} (ID: {$city->id})");
        }
        
        // Check what branch cities exist
        $this->info("\n🏢 BRANCH CITIES:");
        $branchCities = \DB::table('branch_cities')
            ->join('provider_branches', 'branch_cities.provider_branch_id', '=', 'provider_branches.id')
            ->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
            ->join('cities', 'branch_cities.city_id', '=', 'cities.id')
            ->select('cities.name as city_name', 'cities.id as city_id', 'provider_branches.branch_name', 'providers.name as provider_name', 'providers.country_id')
            ->get();
        
        $this->line("Total branch cities: " . $branchCities->count());
        
        foreach ($branchCities as $bc) {
            $this->line("  - {$bc->city_name} (ID: {$bc->city_id}) -> {$bc->branch_name} (Provider: {$bc->provider_name})");
        }
        
        $this->info("\n✅ Debug completed!");
    }
}
