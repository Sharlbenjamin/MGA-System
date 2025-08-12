<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestShouldBePaidFilters extends Command
{
    protected $signature = 'test:should-be-paid-filters';
    protected $description = 'Test the filter queries for ShouldBePaidResource';

    public function handle()
    {
        $this->info('=== Testing ShouldBePaid Filters ===');
        
        // Base query (same as getEloquentQuery)
        $baseQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts', 'branch', 'file.providerBranch.provider', 'file.invoices'])
            ->orderBy('due_date', 'asc');
        
        $this->info("Base query count: " . $baseQuery->count());
        
        // Test Overdue Bills filter
        $this->info("\n=== Testing Overdue Bills Filter ===");
        $overdueQuery = (clone $baseQuery)->where('due_date', '<', now());
        $overdueCount = $overdueQuery->count();
        $this->info("Overdue bills count: {$overdueCount}");
        
        if ($overdueCount > 0) {
            $this->info("Sample overdue bills:");
            $overdueQuery->limit(3)->get()->each(function ($bill) {
                $this->line("  - {$bill->name} (Due: {$bill->due_date})");
            });
        }
        
        // Test BK Received Bills filter
        $this->info("\n=== Testing BK Received Bills Filter ===");
        $bkReceivedQuery = (clone $baseQuery)->whereHas('file', function (Builder $fileQuery) {
            $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                $invoiceQuery->where('status', 'Paid');
            });
        });
        $bkReceivedCount = $bkReceivedQuery->count();
        $this->info("BK Received bills count: {$bkReceivedCount}");
        
        if ($bkReceivedCount > 0) {
            $this->info("Sample BK received bills:");
            $bkReceivedQuery->limit(3)->get()->each(function ($bill) {
                $this->line("  - {$bill->name}");
                if ($bill->file && $bill->file->invoices) {
                    $paidInvoices = $bill->file->invoices->where('status', 'Paid');
                    $this->line("    Paid invoices: " . $paidInvoices->count());
                }
            });
        }
        
        // Test Missing Documents filter
        $this->info("\n=== Testing Missing Documents Filter ===");
        $missingDocsQuery = (clone $baseQuery)->where(function (Builder $subQuery) {
            $subQuery->whereNull('bill_google_link')
                   ->orWhere('bill_google_link', '');
        });
        $missingDocsCount = $missingDocsQuery->count();
        $this->info("Missing documents count: {$missingDocsCount}");
        
        if ($missingDocsCount > 0) {
            $this->info("Sample bills with missing documents:");
            $missingDocsQuery->limit(3)->get()->each(function ($bill) {
                $this->line("  - {$bill->name} (Google Link: " . ($bill->bill_google_link ?: 'NULL') . ")");
            });
        }
        
        // Test combined filters
        $this->info("\n=== Testing Combined Filters ===");
        $combinedQuery = (clone $baseQuery)
            ->where('due_date', '<', now())
            ->whereHas('file', function (Builder $fileQuery) {
                $fileQuery->whereHas('invoices', function (Builder $invoiceQuery) {
                    $invoiceQuery->where('status', 'Paid');
                });
            });
        $combinedCount = $combinedQuery->count();
        $this->info("Overdue + BK Received count: {$combinedCount}");
        
        $this->info("\n=== Filter Test Complete ===");
    }
}
