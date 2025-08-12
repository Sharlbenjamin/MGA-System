<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugLiveBillGrouping extends Command
{
    protected $signature = 'debug:live-bill-grouping';
    protected $description = 'Debug bill grouping issues on live server';

    public function handle()
    {
        $this->info('=== Live Server Bill Grouping Debug ===');
        
        // Get all unpaid/partial bills with relationships
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider'])
            ->get();
        
        $this->info("Total unpaid/partial bills: {$bills->count()}");
        
        if ($bills->count() === 0) {
            $this->warn('No unpaid/partial bills found!');
            return;
        }
        
        // Check for data inconsistencies
        $this->info("\n=== Data Consistency Check ===");
        
        $nullProviderId = $bills->whereNull('provider_id')->count();
        $nullBranchId = $bills->whereNull('branch_id')->count();
        $nullProviderRelation = $bills->filter(fn($b) => $b->provider === null)->count();
        $nullBranchRelation = $bills->filter(fn($b) => $b->branch === null)->count();
        
        $this->line("Bills with null provider_id: {$nullProviderId}");
        $this->line("Bills with null branch_id: {$nullBranchId}");
        $this->line("Bills with null provider relation: {$nullProviderRelation}");
        $this->line("Bills with null branch relation: {$nullBranchRelation}");
        
        // Check bills that have provider_id but no provider relation
        $orphanedProviderBills = $bills->filter(function($bill) {
            return $bill->provider_id && !$bill->provider;
        });
        $this->warn("Bills with provider_id but no provider relation: {$orphanedProviderBills->count()}");
        
        // Check bills that have branch_id but no branch relation
        $orphanedBranchBills = $bills->filter(function($bill) {
            return $bill->branch_id && !$bill->branch;
        });
        $this->warn("Bills with branch_id but no branch relation: {$orphanedBranchBills->count()}");
        
        // Test grouping logic
        $this->info("\n=== Grouping Test ===");
        
        // Test provider grouping
        $providerGroups = $bills->groupBy(function($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups found: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $this->line("- {$providerName}: {$groupBills->count()} bills");
        }
        
        // Test branch grouping
        $branchGroups = $bills->groupBy(function($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("\nBranch groups found: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $this->line("- {$branchName}: {$groupBills->count()} bills");
        }
        
        // Show sample problematic bills
        if ($orphanedProviderBills->count() > 0) {
            $this->info("\n=== Sample Problematic Bills ===");
            $orphanedProviderBills->take(3)->each(function($bill) {
                $this->line("Bill ID: {$bill->id}, Name: {$bill->name}");
                $this->line("  - provider_id: {$bill->provider_id}");
                $this->line("  - branch_id: {$bill->branch_id}");
                $this->line("  - File: {$bill->file?->mga_reference}");
                $this->line("  - File provider_branch_id: {$bill->file?->provider_branch_id}");
                $this->line("---");
            });
        }
        
        // Test the accessor methods
        $this->info("\n=== Accessor Method Test ===");
        $sampleBill = $bills->first();
        if ($sampleBill) {
            $this->line("Sample bill provider_name: " . $sampleBill->provider_name);
            $this->line("Sample bill branch_name: " . $sampleBill->branch_name);
        }
        
        $this->info("\n=== Debug Complete ===");
    }
}
