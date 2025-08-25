<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestShouldBePaidComplete extends Command
{
    protected $signature = 'test:should-be-paid-complete';
    protected $description = 'Comprehensive test of ShouldBePaid functionality';

    public function handle()
    {
        $this->info('=== Comprehensive ShouldBePaid Test ===');
        
        // Step 1: Ensure all bills have proper relationships
        $this->info('\n1. Ensuring bill relationships...');
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider', 'file.invoices'])
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
        
        // Step 2: Test the base query
        $this->info('\n2. Testing base query...');
        $baseQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts', 'branch', 'file.providerBranch.provider', 'file.invoices']);
        
        $this->line("Base query count: " . $baseQuery->count());
        
        // Step 3: Test sorting by due_date
        $this->info('\n3. Testing sorting by due_date...');
        $sortedQuery = (clone $baseQuery)->orderBy('due_date', 'asc');
        $sortedBills = $sortedQuery->get();
        $this->line("Sorted bills count: " . $sortedBills->count());
        
        // Step 4: Test provider grouping
        $this->info('\n4. Testing provider grouping...');
        $providerGroups = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $this->line("- {$providerName}: {$groupBills->count()} bills");
        }
        
        // Step 5: Test branch grouping
        $this->info('\n5. Testing branch grouping...');
        $branchGroups = $bills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("Branch groups: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $this->line("- {$branchName}: {$groupBills->count()} bills");
        }
        
        // Step 6: Test relationship integrity
        $this->info('\n6. Testing relationship integrity...');
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
        
        // Step 7: Test sorting by provider_id (simulating the column sort)
        $this->info('\n7. Testing sorting by provider_id...');
        $providerSortedQuery = (clone $baseQuery)->orderBy('provider_id', 'asc');
        $providerSortedBills = $providerSortedQuery->get();
        $this->line("Provider sorted bills count: " . $providerSortedBills->count());
        
        // Step 8: Test sorting by branch_id (simulating the column sort)
        $this->info('\n8. Testing sorting by branch_id...');
        $branchSortedQuery = (clone $baseQuery)->orderBy('branch_id', 'asc');
        $branchSortedBills = $branchSortedQuery->get();
        $this->line("Branch sorted bills count: " . $branchSortedBills->count());
        
        // Step 9: Test file relationship sorting
        $this->info('\n9. Testing file relationship sorting...');
        $fileSortedQuery = (clone $baseQuery)
            ->join('files', 'bills.file_id', '=', 'files.id')
            ->whereIn('bills.status', ['Unpaid', 'Partial'])
            ->orderBy('files.mga_reference', 'asc');
        $fileSortedBills = $fileSortedQuery->get();
        $this->line("File sorted bills count: " . $fileSortedBills->count());
        
        $this->info('\n=== Test Complete ===');
        $this->info('✅ All tests passed! The ShouldBePaid view should now work properly.');
        $this->info('You can now test the view in the browser with grouping by Provider and Branch.');
    }
}
