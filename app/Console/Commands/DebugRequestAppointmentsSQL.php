<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\BranchService;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\Country;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DebugRequestAppointmentsSQL extends Command
{
    protected $signature = 'debug:request-sql {fileId} {--service-type=} {--country=} {--city=} {--status=}';
    protected $description = 'Debug the SQL queries in RequestAppointments page';

    public function handle()
    {
        $fileId = $this->argument('fileId');
        $serviceTypeId = $this->option('service-type');
        $countryId = $this->option('country');
        $cityId = $this->option('city');
        $status = $this->option('status');

        $this->info('=== DEBUGGING REQUEST APPOINTMENTS SQL ===');
        
        // Load the file
        $file = File::with(['patient', 'serviceType', 'city', 'country'])->find($fileId);
        if (!$file) {
            $this->error("File with ID {$fileId} not found!");
            return;
        }

        $this->info("\n📄 FILE DETAILS:");
        $this->line("ID: {$file->id}");
        $this->line("Patient: {$file->patient->name}");
        $this->line("Service Type: {$file->serviceType->name} (ID: {$file->service_type_id})");
        $this->line("City: {$file->city->name} (ID: {$file->city_id})");
        $this->line("Country: {$file->country->name} (ID: {$file->country_id})");

        // Test base query
        $this->info("\n🔍 TESTING BASE QUERY:");
        $baseQuery = $this->getBaseQuery();
        $this->showQueryResults($baseQuery, 'Base Query');

        // Test with service type filter
        if ($serviceTypeId) {
            $this->info("\n🏥 TESTING SERVICE TYPE FILTER (ID: {$serviceTypeId}):");
            $serviceQuery = $baseQuery->where('branch_services.service_type_id', $serviceTypeId);
            $this->showQueryResults($serviceQuery, 'Service Type Filter');
        }

        // Test with country filter
        if ($countryId) {
            $this->info("\n🏳️ TESTING COUNTRY FILTER (ID: {$countryId}):");
            $countryQuery = $baseQuery->where('providers.country_id', $countryId);
            $this->showQueryResults($countryQuery, 'Country Filter');
        }

        // Test with city filter
        if ($cityId) {
            $this->info("\n🏙️ TESTING CITY FILTER (ID: {$cityId}):");
            $cityQuery = $baseQuery->whereExists(function($subQuery) use ($cityId) {
                $subQuery->select(DB::raw(1))
                    ->from('branch_cities')
                    ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                    ->where('branch_cities.city_id', $cityId);
            });
            $this->showQueryResults($cityQuery, 'City Filter');
        }

        // Test with status filter
        if ($status) {
            $this->info("\n📊 TESTING STATUS FILTER ({$status}):");
            $statusQuery = $baseQuery->where('providers.status', $status);
            $this->showQueryResults($statusQuery, 'Status Filter');
        }

        // Test combined filters
        $this->info("\n🎯 TESTING COMBINED FILTERS:");
        $combinedQuery = $baseQuery;
        
        if ($serviceTypeId) {
            $combinedQuery = $combinedQuery->where('branch_services.service_type_id', $serviceTypeId);
        }
        if ($countryId) {
            $combinedQuery = $combinedQuery->where('providers.country_id', $countryId);
        }
        if ($cityId) {
            $combinedQuery = $combinedQuery->whereExists(function($subQuery) use ($cityId) {
                $subQuery->select(DB::raw(1))
                    ->from('branch_cities')
                    ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                    ->where('branch_cities.city_id', $cityId);
            });
        }
        if ($status) {
            $combinedQuery = $combinedQuery->where('providers.status', $status);
        }
        
        $this->showQueryResults($combinedQuery, 'Combined Filters');

        // Show the actual SQL
        $this->info("\n🔧 ACTUAL SQL QUERIES:");
        $this->line("Base Query SQL:");
        $this->line($baseQuery->toSql());
        $this->line("Base Query Bindings: " . json_encode($baseQuery->getBindings()));
        
        if ($combinedQuery !== $baseQuery) {
            $this->line("\nCombined Query SQL:");
            $this->line($combinedQuery->toSql());
            $this->line("Combined Query Bindings: " . json_encode($combinedQuery->getBindings()));
        }

        // Test with file's actual values
        $this->info("\n📋 TESTING WITH FILE'S ACTUAL VALUES:");
        $fileQuery = $baseQuery
            ->where('branch_services.service_type_id', $file->service_type_id)
            ->where('providers.country_id', $file->country_id)
            ->whereExists(function($subQuery) use ($file) {
                $subQuery->select(DB::raw(1))
                    ->from('branch_cities')
                    ->whereColumn('branch_cities.provider_branch_id', 'provider_branches.id')
                    ->where('branch_cities.city_id', $file->city_id);
            })
            ->where('providers.status', 'Active');
        
        $this->showQueryResults($fileQuery, 'File Values Combined');
    }

    private function getBaseQuery(): Builder
    {
        return BranchService::with([
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
    }

    private function showQueryResults(Builder $query, string $label): void
    {
        $results = $query->get();
        $this->line("{$label} results: {$results->count()} branch services");
        
        if ($results->count() > 0) {
            foreach ($results as $record) {
                $this->line("  - {$record->providerBranch->branch_name} (Provider: {$record->providerBranch->provider->name})");
            }
        } else {
            $this->line("  No results found");
        }
    }
}
