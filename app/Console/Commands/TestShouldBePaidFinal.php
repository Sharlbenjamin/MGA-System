<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestShouldBePaidFinal extends Command
{
    protected $signature = 'test:should-be-paid-final';
    protected $description = 'Final test of ShouldBePaid functionality using BillResource approach';

    public function handle()
    {
        $this->info('=== Final ShouldBePaid Test (BillResource Approach) ===');
        
        // Test the exact same approach as BillResource
        $this->info('Testing BillResource-style approach...');
        
        // Base query (same as getEloquentQuery)
        $baseQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider', 'file.invoices']);
        
        $this->line("Base query count: " . $baseQuery->count());
        
        // Test grouping by provider.name (same as BillResource)
        $this->info('\nTesting grouping by provider.name...');
        $bills = $baseQuery->get();
        $providerGroups = $bills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $this->line("- {$providerName}: {$groupBills->count()} bills");
        }
        
        // Test grouping by branch.branch_name (same as BillResource)
        $this->info('\nTesting grouping by branch.branch_name...');
        $branchGroups = $bills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("Branch groups: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $this->line("- {$branchName}: {$groupBills->count()} bills");
        }
        
        // Test sorting by provider.name
        $this->info('\nTesting sorting by provider.name...');
        try {
            $providerSortedQuery = (clone $baseQuery)->orderBy('provider.name', 'asc');
            $this->line("Provider.name sorting works: " . ($providerSortedQuery->count() > 0 ? 'Yes' : 'No'));
        } catch (\Exception $e) {
            $this->error("Provider.name sorting error: " . $e->getMessage());
        }
        
        // Test sorting by branch.branch_name
        $this->info('\nTesting sorting by branch.branch_name...');
        try {
            $branchSortedQuery = (clone $baseQuery)->orderBy('branch.branch_name', 'asc');
            $this->line("Branch.branch_name sorting works: " . ($branchSortedQuery->count() > 0 ? 'Yes' : 'No'));
        } catch (\Exception $e) {
            $this->error("Branch.branch_name sorting error: " . $e->getMessage());
        }
        
        // Test the exact failing query
        $this->info('\nTesting the exact failing query...');
        try {
            $failingQuery = (clone $baseQuery)->orderBy('provider', 'asc')->orderBy('due_date', 'asc');
            $this->line("Failing query works: " . ($failingQuery->count() > 0 ? 'Yes' : 'No'));
        } catch (\Exception $e) {
            $this->error("Failing query error: " . $e->getMessage());
        }
        
        $this->info('\n=== Test Complete ===');
        $this->info('The ShouldBePaid resource should now work exactly like BillResource.');
        $this->info('If there are still sorting issues, they will be the same issues that BillResource has.');
    }
}
