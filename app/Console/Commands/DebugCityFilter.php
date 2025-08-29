<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\BranchService;
use App\Models\ProviderBranch;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DebugCityFilter extends Command
{
    protected $signature = 'debug:city-filter {fileId}';
    protected $description = 'Debug the city filter specifically';

    public function handle()
    {
        $fileId = $this->argument('fileId');
        
        $this->info('=== DEBUGGING CITY FILTER ===');
        
        // Load the file
        $file = File::with(['patient', 'serviceType', 'city', 'country'])->find($fileId);
        if (!$file) {
            $this->error("File with ID {$fileId} not found!");
            return;
        }

        $this->info("\n📄 FILE DETAILS:");
        $this->line("ID: {$file->id}");
        $this->line("Patient: {$file->patient->name}");
        $this->line("City: {$file->city->name} (ID: {$file->city_id})");
        $this->line("Country: {$file->country->name} (ID: {$file->country_id})");

        // Check what's in branch_cities for this city
        $this->info("\n🏙️ BRANCH CITIES FOR CITY {$file->city->name} (ID: {$file->city_id}):");
        $branchCities = DB::table('branch_cities')
            ->join('cities', 'branch_cities.city_id', '=', 'cities.id')
            ->join('provider_branches', 'branch_cities.provider_branch_id', '=', 'provider_branches.id')
            ->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
            ->where('branch_cities.city_id', $file->city_id)
            ->select(
                'cities.name as city_name',
                'cities.id as city_id',
                'provider_branches.branch_name',
                'provider_branches.id as branch_id',
                'providers.name as provider_name',
                'providers.country_id'
            )
            ->get();

        if ($branchCities->count() > 0) {
            foreach ($branchCities as $bc) {
                $this->line("  - City: {$bc->city_name} -> Branch: {$bc->branch_name} (Provider: {$bc->provider_name})");
            }
        } else {
            $this->line("  No branch cities found for this city!");
        }

        // Check what branch services exist for these branches
        $this->info("\n🏥 BRANCH SERVICES FOR THESE BRANCHES:");
        if ($branchCities->count() > 0) {
            $branchIds = $branchCities->pluck('branch_id')->toArray();
            $branchServices = BranchService::whereIn('provider_branch_id', $branchIds)
                ->where('is_active', 1)
                ->with(['providerBranch.provider', 'serviceType'])
                ->get();

            foreach ($branchServices as $bs) {
                $this->line("  - Branch: {$bs->providerBranch->branch_name} -> Service: {$bs->serviceType->name}");
            }
        }

        // Test the actual query that's being used
        $this->info("\n🔍 TESTING THE ACTUAL QUERY:");
        $baseQuery = BranchService::with([
            'providerBranch.provider.country',
            'serviceType'
        ])
        ->join('provider_branches', 'branch_services.provider_branch_id', '=', 'provider_branches.id')
        ->join('providers', 'provider_branches.provider_id', '=', 'providers.id')
        ->where('branch_services.is_active', 1);

        $this->line("Base query results: " . $baseQuery->count() . " branch services");

        // Test with city filter
        $cityQuery = $baseQuery->whereExists(function($subQuery) use ($file) {
            $subQuery->select(DB::raw(1))
                ->from('branch_cities')
                ->whereColumn('branch_cities.provider_branch_id', 'branch_services.provider_branch_id')
                ->where('branch_cities.city_id', $file->city_id);
        });

        $this->line("City filter results: " . $cityQuery->count() . " branch services");

        // Show the actual results
        $results = $cityQuery->get();
        foreach ($results as $result) {
            $this->line("  - {$result->providerBranch->branch_name} (Provider: {$result->providerBranch->provider->name})");
        }

        // Show the SQL
        $this->info("\n🔧 ACTUAL SQL:");
        $this->line($cityQuery->toSql());
        $this->line("Bindings: " . json_encode($cityQuery->getBindings()));

        // Check if there are any branch_cities at all
        $this->info("\n📊 ALL BRANCH CITIES:");
        $allBranchCities = DB::table('branch_cities')
            ->join('cities', 'branch_cities.city_id', '=', 'cities.id')
            ->join('provider_branches', 'branch_cities.provider_branch_id', '=', 'provider_branches.id')
            ->select('cities.name as city_name', 'cities.id as city_id', 'provider_branches.branch_name')
            ->orderBy('cities.name')
            ->get()
            ->groupBy('city_name');

        foreach ($allBranchCities as $cityName => $branches) {
            $this->line("  {$cityName}: " . $branches->count() . " branches");
        }
    }
}
