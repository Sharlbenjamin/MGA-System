<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class TestShouldBePaidGrouping extends Command
{
    protected $signature = 'test:should-be-paid-grouping';
    protected $description = 'Test the ShouldBePaid grouping functionality';

    public function handle()
    {
        $this->info('=== Testing ShouldBePaid Grouping ===');
        
        // First, ensure all bills have proper relationships
        $this->info('Ensuring bill relationships...');
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider'])
            ->get();
        
        $fixedCount = 0;
        foreach ($bills as $bill) {
            $bill->ensureProviderAndBranchRelationships();
            if ($bill->wasChanged(['provider_id', 'branch_id'])) {
                $fixedCount++;
            }
        }
        
        if ($fixedCount > 0) {
            $this->info("Fixed {$fixedCount} bills");
        }
        
        // Test provider grouping
        $this->info("\n=== Testing Provider Grouping ===");
        $providerGroups = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups found: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $this->line("- {$providerName}: {$groupBills->count()} bills");
        }
        
        // Test branch grouping
        $this->info("\n=== Testing Branch Grouping ===");
        $branchGroups = $bills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("Branch groups found: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $this->line("- {$branchName}: {$groupBills->count()} bills");
        }
        
        // Check for any remaining null relationships
        $nullProviderBills = $bills->whereNull('provider_id');
        $nullBranchBills = $bills->whereNull('branch_id');
        
        if ($nullProviderBills->count() > 0) {
            $this->warn("⚠️ {$nullProviderBills->count()} bills still have null provider_id");
        }
        
        if ($nullBranchBills->count() > 0) {
            $this->warn("⚠️ {$nullBranchBills->count()} bills still have null branch_id");
        }
        
        if ($nullProviderBills->count() === 0 && $nullBranchBills->count() === 0) {
            $this->info("✅ All bills have proper relationships!");
        }
        
        $this->info("\n=== Test Complete ===");
        $this->info("The ShouldBePaid view should now work properly with grouping by Provider and Branch.");
    }
}
