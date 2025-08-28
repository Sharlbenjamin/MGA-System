<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\City;
use App\Models\Country;

class TestFile144Filters extends Command
{
    protected $signature = 'test:file-144';
    protected $description = 'Test file ID 144 and analyze all filtering relationships';

    public function handle()
    {
        $this->info('=== TESTING FILE ID 144 ===');
        
        // Load file with all relationships
        $file = File::with(['patient', 'serviceType', 'city', 'country'])->find(144);
        
        if (!$file) {
            $this->error('File ID 144 not found!');
            return;
        }
        
        $this->info("\n📄 FILE DETAILS:");
        $this->line("ID: {$file->id}");
        $this->line("Patient: " . ($file->patient->name ?? 'N/A'));
        $this->line("Service Type: " . ($file->serviceType->name ?? 'N/A') . " (ID: {$file->service_type_id})");
        $this->line("City: " . ($file->city->name ?? 'N/A') . " (ID: {$file->city_id})");
        $this->line("Country: " . ($file->country->name ?? 'N/A') . " (ID: {$file->country_id})");
        $this->line("Address: {$file->address}");
        
        $this->info("\n🔍 ANALYZING PROVIDERS AND BRANCHES:");
        
        // Get all providers with branches
        $providers = Provider::with(['branches.cities', 'branches.branchServices.serviceType', 'country'])
            ->whereHas('branches')
            ->get();
            
        $this->line("Total providers with branches: " . $providers->count());
        
        // Show providers in the file's country
        $countryProviders = $providers->where('country_id', $file->country_id);
        $this->line("Providers in {$file->country->name}: " . $countryProviders->count());
        
        foreach ($countryProviders as $provider) {
            $this->line("\n  Provider: {$provider->name} (Status: {$provider->status})");
            $this->line("  Branches: " . $provider->branches->count());
            
            foreach ($provider->branches as $branch) {
                $cities = $branch->cities ? $branch->cities->pluck('name')->join(', ') : 'None';
                $services = $branch->branchServices ? $branch->branchServices->pluck('serviceType.name')->join(', ') : 'None';
                
                $this->line("    - Branch: {$branch->name}");
                $this->line("      Cities: {$cities}");
                $this->line("      Services: {$services}");
                $this->line("      Priority: {$branch->priority}");
                $this->line("      Email: " . ($branch->email ?: 'None'));
            }
        }
        
        $this->info("\n🎯 EXPECTED FILTER RESULTS:");
        
        // Test the exact filters that should work
        $this->line("For filters: Country={$file->country->name}, Service Type={$file->serviceType->name}, City={$file->city->name}");
        
        $matchingBranches = collect();
        
        foreach ($countryProviders as $provider) {
            foreach ($provider->branches as $branch) {
                // Check if branch has the service type
                $hasService = $branch->branchServices && $branch->branchServices->contains('service_type_id', $file->service_type_id);
                
                // Check if branch has the city
                $hasCity = $branch->cities && $branch->cities->contains('id', $file->city_id);
                
                if ($hasService && $hasCity) {
                    $matchingBranches->push([
                        'provider' => $provider->name,
                        'branch' => $branch->name,
                        'priority' => $branch->priority,
                        'email' => $branch->email ?: 'None'
                    ]);
                }
            }
        }
        
        if ($matchingBranches->count() > 0) {
            $this->line("✅ Expected results ({$matchingBranches->count()} branches):");
            foreach ($matchingBranches as $branch) {
                $this->line("  - {$branch['provider']} > {$branch['branch']} (Priority: {$branch['priority']}, Email: {$branch['email']})");
            }
        } else {
            $this->line("❌ No branches match the exact filters!");
        }
        
        $this->info("\n🔧 DEBUGGING INDIVIDUAL FILTERS:");
        
        // Test each filter individually
        $this->line("\n1. Country filter only ({$file->country->name}):");
        $countryBranches = collect();
        foreach ($countryProviders as $provider) {
            foreach ($provider->branches as $branch) {
                $countryBranches->push("{$provider->name} > {$branch->name}");
            }
        }
        $this->line("   Results: " . $countryBranches->count() . " branches");
        
        // Test service type filter
        $this->line("\n2. Service type filter only ({$file->serviceType->name}):");
        $serviceBranches = collect();
        foreach ($providers as $provider) {
            foreach ($provider->branches as $branch) {
                if ($branch->branchServices && $branch->branchServices->contains('service_type_id', $file->service_type_id)) {
                    $serviceBranches->push("{$provider->name} > {$branch->name}");
                }
            }
        }
        $this->line("   Results: " . $serviceBranches->count() . " branches");
        
        // Test city filter
        $this->line("\n3. City filter only ({$file->city->name}):");
        $cityBranches = collect();
        foreach ($providers as $provider) {
            foreach ($provider->branches as $branch) {
                if ($branch->cities && $branch->cities->contains('id', $file->city_id)) {
                    $cityBranches->push("{$provider->name} > {$branch->name}");
                }
            }
        }
        $this->line("   Results: " . $cityBranches->count() . " branches");
        
        $this->info("\n✅ Test completed!");
    }
}
