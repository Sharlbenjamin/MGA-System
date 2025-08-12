<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugBillGrouping extends Command
{
    protected $signature = 'debug:bill-grouping';
    protected $description = 'Debug bill grouping issues by checking provider and branch relationships';

    public function handle()
    {
        $this->info('Checking bill grouping relationships...');
        
        // Get all unpaid/partial bills
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider'])
            ->get();
        
        $this->info("Total bills found: {$bills->count()}");
        
        // Check bills with null provider_id
        $billsWithNullProvider = $bills->whereNull('provider_id');
        $this->warn("Bills with null provider_id: {$billsWithNullProvider->count()}");
        
        // Check bills with null branch_id
        $billsWithNullBranch = $bills->whereNull('branch_id');
        $this->warn("Bills with null branch_id: {$billsWithNullBranch->count()}");
        
        // Check bills where provider relationship is null
        $billsWithNullProviderRelation = $bills->filter(function ($bill) {
            return $bill->provider === null;
        });
        $this->warn("Bills with null provider relationship: {$billsWithNullProviderRelation->count()}");
        
        // Check bills where branch relationship is null
        $billsWithNullBranchRelation = $bills->filter(function ($bill) {
            return $bill->branch === null;
        });
        $this->warn("Bills with null branch relationship: {$billsWithNullBranchRelation->count()}");
        
        // Show some examples of problematic bills
        if ($billsWithNullProvider->count() > 0) {
            $this->info("\nExamples of bills with null provider_id:");
            $billsWithNullProvider->take(5)->each(function ($bill) {
                $this->line("- Bill ID: {$bill->id}, Name: {$bill->name}, File: {$bill->file?->mga_reference}");
            });
        }
        
        if ($billsWithNullBranch->count() > 0) {
            $this->info("\nExamples of bills with null branch_id:");
            $billsWithNullBranch->take(5)->each(function ($bill) {
                $this->line("- Bill ID: {$bill->id}, Name: {$bill->name}, File: {$bill->file?->mga_reference}");
            });
        }
        
        // Check if bills have file but file doesn't have providerBranch
        $billsWithFileButNoProviderBranch = $bills->filter(function ($bill) {
            return $bill->file && !$bill->file->providerBranch;
        });
        $this->warn("Bills with file but no providerBranch: {$billsWithFileButNoProviderBranch->count()}");
        
        // Group bills by provider to see what we get
        $this->info("\nGrouping bills by provider:");
        $groupedByProvider = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        foreach ($groupedByProvider as $providerName => $providerBills) {
            $this->line("- {$providerName}: {$providerBills->count()} bills");
        }
        
        // Group bills by branch to see what we get
        $this->info("\nGrouping bills by branch:");
        $groupedByBranch = $bills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        foreach ($groupedByBranch as $branchName => $branchBills) {
            $this->line("- {$branchName}: {$branchBills->count()} bills");
        }
        
        $this->info("\nDebug completed!");
    }
}
