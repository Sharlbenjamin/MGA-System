<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\ServiceType;
use App\Models\BranchService;
use Illuminate\Database\Eloquent\Builder;

class TestServiceTypeFilter extends Command
{
    protected $signature = 'test:service-filter {serviceTypeId?}';
    protected $description = 'Test the service type filter for ProviderBranchResource';

    public function handle()
    {
        $serviceTypeId = $this->argument('serviceTypeId');
        
        $this->info('=== TESTING SERVICE TYPE FILTER ===');
        
        // Show all service types
        $this->info("\n📋 Available Service Types:");
        $serviceTypes = ServiceType::all();
        foreach ($serviceTypes as $serviceType) {
            $this->line("  - {$serviceType->id}: {$serviceType->name}");
        }
        
        if ($serviceTypeId) {
            $this->testSpecificServiceType($serviceTypeId);
        } else {
            $this->testAllServiceTypes();
        }
    }
    
    private function testSpecificServiceType($serviceTypeId)
    {
        $serviceType = ServiceType::find($serviceTypeId);
        if (!$serviceType) {
            $this->error("Service type with ID {$serviceTypeId} not found!");
            return;
        }
        
        $this->info("\n🎯 Testing Service Type: {$serviceType->name} (ID: {$serviceTypeId})");
        
        // Test the exact same query as the filter
        $query = ProviderBranch::query();
        $query->whereHas('branchServices', function (Builder $query) use ($serviceTypeId) {
            $query->where('service_type_id', $serviceTypeId)
                  ->where('is_active', 1);
        });
        
        $results = $query->with(['provider', 'branchServices.serviceType'])->get();
        
        $this->info("Found {$results->count()} branches with this service type:");
        
        foreach ($results as $branch) {
            $services = $branch->branchServices()
                ->where('is_active', 1)
                ->with('serviceType')
                ->get()
                ->pluck('serviceType.name')
                ->implode(', ');
                
            $this->line("  - {$branch->branch_name} (Provider: {$branch->provider->name})");
            $this->line("    Services: {$services}");
        }
    }
    
    private function testAllServiceTypes()
    {
        $this->info("\n🔍 Testing All Service Types:");
        
        foreach (ServiceType::all() as $serviceType) {
            $query = ProviderBranch::query();
            $query->whereHas('branchServices', function (Builder $query) use ($serviceType) {
                $query->where('service_type_id', $serviceType->id)
                      ->where('is_active', 1);
            });
            
            $count = $query->count();
            $this->line("  - {$serviceType->name}: {$count} branches");
        }
        
        $this->info("\n📊 Summary:");
        $this->info("Total Provider Branches: " . ProviderBranch::count());
        $this->info("Total Branch Services: " . BranchService::where('is_active', 1)->count());
        $this->info("Active Branch Services: " . BranchService::where('is_active', 1)->count());
    }
}
