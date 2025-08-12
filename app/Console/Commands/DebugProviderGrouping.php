<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugProviderGrouping extends Command
{
    protected $signature = 'debug:provider-grouping';
    protected $description = 'Debug provider grouping issue in ShouldBePaidResource';

    public function handle()
    {
        $this->info('=== Debugging Provider Grouping Issue ===');
        
        // Get all unpaid/partial bills with provider relationships
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.invoices'])
            ->get();
        
        $this->info("Total unpaid/partial bills: {$bills->count()}");
        
        if ($bills->count() === 0) {
            $this->warn('No unpaid/partial bills found!');
            return;
        }
        
        // Check for duplicate provider IDs
        $providerIds = $bills->pluck('provider_id')->filter();
        $uniqueProviderIds = $providerIds->unique();
        
        $this->info("Unique provider IDs: " . $uniqueProviderIds->count());
        $this->info("Total provider IDs: " . $providerIds->count());
        
        if ($providerIds->count() !== $uniqueProviderIds->count()) {
            $this->warn("⚠️ Duplicate provider IDs found!");
        }
        
        // Group bills by provider_id and analyze
        $groupedByProviderId = $bills->groupBy('provider_id');
        
        $this->info("\n=== Bills Grouped by Provider ID ===");
        foreach ($groupedByProviderId as $providerId => $providerBills) {
            $provider = $providerBills->first()->provider;
            $providerName = $provider ? $provider->name : 'No Provider';
            
            $this->line("Provider ID: {$providerId}");
            $this->line("Provider Name: {$providerName}");
            $this->line("Number of bills: {$providerBills->count()}");
            
            // Show bill details
            foreach ($providerBills as $bill) {
                $this->line("  - Bill ID: {$bill->id}, Name: {$bill->name}");
            }
            $this->line("---");
        }
        
        // Check for null provider_id
        $billsWithNullProvider = $bills->whereNull('provider_id');
        if ($billsWithNullProvider->count() > 0) {
            $this->warn("\n⚠️ Bills with null provider_id: {$billsWithNullProvider->count()}");
            foreach ($billsWithNullProvider as $bill) {
                $this->line("  - Bill ID: {$bill->id}, Name: {$bill->name}");
            }
        }
        
        // Check for bills with provider but null provider_id
        $billsWithProviderButNullId = $bills->filter(function ($bill) {
            return $bill->provider_id === null && $bill->provider !== null;
        });
        
        if ($billsWithProviderButNullId->count() > 0) {
            $this->warn("\n⚠️ Bills with provider relationship but null provider_id: {$billsWithProviderButNullId->count()}");
        }
        
        // Test the exact grouping logic that Filament uses
        $this->info("\n=== Testing Filament Grouping Logic ===");
        
        $groupedByProviderName = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->info("Groups by provider name: " . $groupedByProviderName->count());
        foreach ($groupedByProviderName as $providerName => $providerBills) {
            $this->line("Provider: {$providerName}");
            $this->line("Number of bills: {$providerBills->count()}");
            
            // Check if this provider has multiple provider_id values
            $providerIds = $providerBills->pluck('provider_id')->unique();
            if ($providerIds->count() > 1) {
                $this->warn("  ⚠️ This provider has multiple provider_id values: " . $providerIds->implode(', '));
            }
        }
        
        // Check for duplicate provider names with different IDs
        $providersByName = collect();
        foreach ($bills as $bill) {
            if ($bill->provider) {
                $providersByName->push([
                    'id' => $bill->provider->id,
                    'name' => $bill->provider->name,
                    'bill_id' => $bill->id
                ]);
            }
        }
        
        $duplicateNames = $providersByName->groupBy('name')->filter(function ($group) {
            return $group->pluck('id')->unique()->count() > 1;
        });
        
        if ($duplicateNames->count() > 0) {
            $this->warn("\n⚠️ Duplicate provider names with different IDs:");
            foreach ($duplicateNames as $name => $providers) {
                $this->line("Provider Name: {$name}");
                $this->line("Provider IDs: " . $providers->pluck('id')->unique()->implode(', '));
            }
        }
        
        $this->info("\n=== Debug Complete ===");
    }
}
