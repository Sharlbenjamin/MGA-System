<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugSpecificBills extends Command
{
    protected $signature = 'debug:specific-bills';
    protected $description = 'Debug specific bills that are causing grouping issues';

    public function handle()
    {
        $this->info('=== Debugging Specific Bills ===');
        
        // Check the specific bills mentioned
        $specificBills = Bill::whereIn('name', ['MG084WH-Bill-01', 'MG087WH-Bill-01'])
            ->with(['provider', 'branch', 'file'])
            ->get();
        
        $this->info("Found {$specificBills->count()} specific bills");
        
        foreach ($specificBills as $bill) {
            $this->line("\nBill: {$bill->name}");
            $this->line("  ID: {$bill->id}");
            $this->line("  Provider ID: " . ($bill->provider_id ?? 'NULL'));
            $this->line("  Provider Name: " . ($bill->provider ? $bill->provider->name : 'No Provider'));
            $this->line("  Branch ID: " . ($bill->branch_id ?? 'NULL'));
            $this->line("  Branch Name: " . ($bill->branch ? $bill->branch->branch_name : 'No Branch'));
            $this->line("  File ID: " . ($bill->file_id ?? 'NULL'));
            $this->line("  File Reference: " . ($bill->file ? $bill->file->mga_reference : 'No File'));
        }
        
        // Now check all Dr. Hussain bills
        $this->info("\n=== All Dr. Hussain Bills ===");
        $hussainBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('provider', function ($query) {
                $query->where('name', 'like', '%Hussain%');
            })
            ->with(['provider', 'branch', 'file'])
            ->get();
        
        $this->info("Found {$hussainBills->count()} Dr. Hussain bills");
        
        foreach ($hussainBills as $bill) {
            $this->line("\nBill: {$bill->name}");
            $this->line("  ID: {$bill->id}");
            $this->line("  Provider ID: " . ($bill->provider_id ?? 'NULL'));
            $this->line("  Provider Name: " . ($bill->provider ? $bill->provider->name : 'No Provider'));
            $this->line("  Branch ID: " . ($bill->branch_id ?? 'NULL'));
            $this->line("  Branch Name: " . ($bill->branch ? $bill->branch->branch_name : 'No Branch'));
        }
        
        // Check if there are multiple providers with similar names
        $this->info("\n=== All Providers with 'Hussain' in name ===");
        $hussainProviders = \App\Models\Provider::where('name', 'like', '%Hussain%')->get();
        
        foreach ($hussainProviders as $provider) {
            $this->line("Provider ID: {$provider->id}, Name: {$provider->name}");
            
            $providerBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
                ->where('provider_id', $provider->id)
                ->get();
            
            $this->line("  Bills count: {$providerBills->count()}");
            foreach ($providerBills as $bill) {
                $this->line("    - {$bill->name}");
            }
        }
        
        // Check for bills with null provider_id
        $this->info("\n=== Bills with NULL provider_id ===");
        $nullProviderBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereNull('provider_id')
            ->with(['file'])
            ->get();
        
        foreach ($nullProviderBills as $bill) {
            $this->line("Bill: {$bill->name}");
            $this->line("  File: " . ($bill->file ? $bill->file->mga_reference : 'No File'));
        }
        
        $this->info("\n=== Debug Complete ===");
    }
}
