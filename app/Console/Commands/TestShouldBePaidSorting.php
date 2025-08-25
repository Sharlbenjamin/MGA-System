<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestShouldBePaidSorting extends Command
{
    protected $signature = 'test:should-be-paid-sorting';
    protected $description = 'Test the ShouldBePaid sorting functionality';

    public function handle()
    {
        $this->info('=== Testing ShouldBePaid Sorting ===');
        
        // Test the base query (same as getEloquentQuery)
        $this->info('Testing base query...');
        $baseQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts', 'branch', 'file.providerBranch.provider', 'file.invoices']);
        
        $this->line("Base query SQL: " . $baseQuery->toSql());
        $this->line("Base query count: " . $baseQuery->count());
        
        // Test with sorting by due_date
        $this->info('\nTesting sorting by due_date...');
        $sortedQuery = (clone $baseQuery)->orderBy('due_date', 'asc');
        $this->line("Sorted query SQL: " . $sortedQuery->toSql());
        
        // Test with provider relationship
        $this->info('\nTesting provider relationship...');
        $bills = $baseQuery->get();
        $this->line("Bills with provider: " . $bills->whereNotNull('provider')->count());
        $this->line("Bills without provider: " . $bills->whereNull('provider')->count());
        
        // Test grouping by provider
        $this->info('\nTesting provider grouping...');
        $providerGroups = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $this->line("- {$providerName}: {$groupBills->count()} bills");
        }
        
        // Test grouping by branch
        $this->info('\nTesting branch grouping...');
        $branchGroups = $bills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("Branch groups: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $this->line("- {$branchName}: {$groupBills->count()} bills");
        }
        
        // Test that all bills have proper relationships
        $this->info('\nTesting relationship integrity...');
        $billsWithNullProvider = $bills->whereNull('provider_id');
        $billsWithNullBranch = $bills->whereNull('branch_id');
        
        if ($billsWithNullProvider->count() > 0) {
            $this->warn("⚠️ {$billsWithNullProvider->count()} bills have null provider_id");
        }
        
        if ($billsWithNullBranch->count() > 0) {
            $this->warn("⚠️ {$billsWithNullBranch->count()} bills have null branch_id");
        }
        
        if ($billsWithNullProvider->count() === 0 && $billsWithNullBranch->count() === 0) {
            $this->info("✅ All bills have proper relationships!");
        }
        
        $this->info('\n=== Test Complete ===');
        $this->info('The ShouldBePaid view should now work without SQL errors.');
    }
}
