<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugSummaryCalculations extends Command
{
    protected $signature = 'debug:summary-calculations';
    protected $description = 'Debug summary calculations for ShouldBePaid view';

    public function handle()
    {
        $this->info('=== Summary Calculations Debug ===');
        
        // Get all unpaid/partial bills
        $unpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file.invoices'])
            ->get();
        
        $this->info("Total unpaid/partial bills: {$unpaidBills->count()}");
        
        if ($unpaidBills->count() === 0) {
            $this->warn('No unpaid/partial bills found!');
            return;
        }
        
        // Test table summaries
        $this->info("\n=== Table Summary Calculations ===");
        
        $totalAmount = $unpaidBills->sum('total_amount');
        $paidAmount = $unpaidBills->sum('paid_amount');
        $remainingAmount = $unpaidBills->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });
        
        $this->line("Total Amount: €" . number_format($totalAmount, 2));
        $this->line("Paid Amount: €" . number_format($paidAmount, 2));
        $this->line("Remaining Amount: €" . number_format($remainingAmount, 2));
        
        // Status breakdown
        $statusBreakdown = $unpaidBills->groupBy('status');
        $this->info("\nStatus Breakdown:");
        foreach ($statusBreakdown as $status => $bills) {
            $this->line("- {$status}: {$bills->count()} bills");
        }
        
        // Google Drive link status
        $linkedBills = $unpaidBills->whereNotNull('bill_google_link')->where('bill_google_link', '!=', '');
        $missingBills = $unpaidBills->whereNull('bill_google_link')->orWhere('bill_google_link', '');
        $this->info("\nGoogle Drive Status:");
        $this->line("- Linked: {$linkedBills->count()} bills");
        $this->line("- Missing: {$missingBills->count()} bills");
        
        // Test widget calculations
        $this->info("\n=== Widget Summary Calculations ===");
        
        // Bills with paid invoices (BK received bills)
        $billsWithPaidInvoices = $unpaidBills->filter(function ($bill) {
            return $bill->file && $bill->file->invoices->where('status', 'Paid')->count() > 0;
        });
        
        $this->line("Bills with paid invoices: {$billsWithPaidInvoices->count()}");
        
        // Providers needing payment (with paid invoices)
        $providersNeedingPayment = $billsWithPaidInvoices
            ->pluck('provider_id')
            ->filter()
            ->unique()
            ->count();
        
        $this->line("Providers needing payment (with paid invoices): {$providersNeedingPayment}");
        
        // Total outstanding amount (with paid invoices)
        $totalUnpaidAmount = $billsWithPaidInvoices->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });
        
        $this->line("Total outstanding (with paid invoices): €" . number_format($totalUnpaidAmount, 2));
        
        // All providers needing payment
        $allProvidersNeedingPayment = $unpaidBills
            ->pluck('provider_id')
            ->filter()
            ->unique()
            ->count();
        
        $this->line("All providers needing payment: {$allProvidersNeedingPayment}");
        
        // Total outstanding amount (all unpaid bills)
        $allTotalUnpaidAmount = $unpaidBills->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });
        
        $this->line("All total outstanding: €" . number_format($allTotalUnpaidAmount, 2));
        
        // Bills with bank accounts
        $billsWithBankAccounts = $unpaidBills->whereNotNull('bank_account_id');
        $totalTransfers = $billsWithBankAccounts->pluck('bank_account_id')->unique()->count();
        
        $this->line("Total transfers (bank account groups): {$totalTransfers}");
        
        // Test grouping
        $this->info("\n=== Grouping Test ===");
        
        $providerGroups = $unpaidBills->groupBy(function ($bill) {
            return $bill->provider?->name ?? 'No Provider';
        });
        
        $this->line("Provider groups: " . $providerGroups->count());
        foreach ($providerGroups as $providerName => $groupBills) {
            $groupTotal = $groupBills->sum(function ($bill) {
                return $bill->total_amount - $bill->paid_amount;
            });
            $this->line("- {$providerName}: {$groupBills->count()} bills, €" . number_format($groupTotal, 2));
        }
        
        $branchGroups = $unpaidBills->groupBy(function ($bill) {
            return $bill->branch?->branch_name ?? 'No Branch';
        });
        
        $this->line("\nBranch groups: " . $branchGroups->count());
        foreach ($branchGroups as $branchName => $groupBills) {
            $groupTotal = $groupBills->sum(function ($bill) {
                return $bill->total_amount - $bill->paid_amount;
            });
            $this->line("- {$branchName}: {$groupBills->count()} bills, €" . number_format($groupTotal, 2));
        }
        
        $this->info("\n=== Debug Complete ===");
    }
}
