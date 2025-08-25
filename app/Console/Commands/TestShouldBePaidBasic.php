<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestShouldBePaidBasic extends Command
{
    protected $signature = 'test:should-be-paid-basic';
    protected $description = 'Test basic ShouldBePaid functionality without grouping';

    public function handle()
    {
        $this->info('=== Testing Basic ShouldBePaid Functionality ===');
        
        // Test the base query
        $this->info('Testing base query...');
        $baseQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.providerBranch.provider', 'file.invoices']);
        
        $this->line("Base query count: " . $baseQuery->count());
        
        // Test sorting by due_date
        $this->info('\nTesting sorting by due_date...');
        $sortedQuery = (clone $baseQuery)->orderBy('due_date', 'asc');
        $this->line("Sorted query works: " . ($sortedQuery->count() > 0 ? 'Yes' : 'No'));
        
        // Test sorting by provider_id
        $this->info('\nTesting sorting by provider_id...');
        $providerSortedQuery = (clone $baseQuery)->orderBy('provider_id', 'asc');
        $this->line("Provider sorted query works: " . ($providerSortedQuery->count() > 0 ? 'Yes' : 'No'));
        
        // Test sorting by branch_id
        $this->info('\nTesting sorting by branch_id...');
        $branchSortedQuery = (clone $baseQuery)->orderBy('branch_id', 'asc');
        $this->line("Branch sorted query works: " . ($branchSortedQuery->count() > 0 ? 'Yes' : 'No'));
        
        // Test the exact query that's failing
        $this->info('\nTesting the failing query...');
        try {
            $failingQuery = (clone $baseQuery)->orderBy('provider', 'asc')->orderBy('due_date', 'asc');
            $this->line("Failing query works: " . ($failingQuery->count() > 0 ? 'Yes' : 'No'));
        } catch (\Exception $e) {
            $this->error("Failing query error: " . $e->getMessage());
        }
        
        $this->info('\n=== Test Complete ===');
    }
}
