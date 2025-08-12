<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class TestProviderIbanDisplay extends Command
{
    protected $signature = 'test:provider-iban-display';
    protected $description = 'Test the provider IBAN display functionality';

    public function handle()
    {
        $this->info('Testing provider IBAN display...');
        
        // Get unpaid bills with provider bank account relationships
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider.bankAccounts'])
            ->take(5)
            ->get();
        
        if ($bills->isEmpty()) {
            $this->warn('No unpaid bills found for testing!');
            return;
        }
        
        $this->info("Found {$bills->count()} bills for testing");
        
        // Test the accessor method
        foreach ($bills as $bill) {
            $this->line("Bill: {$bill->name}");
            $this->line("  Provider: " . ($bill->provider?->name ?? 'No Provider'));
            $this->line("  Provider IBAN (accessor): {$bill->provider_bank_iban}");
            
            // Test direct relationship access
            $bankAccount = $bill->provider?->bankAccounts?->first();
            if ($bankAccount) {
                $this->line("  Bank Account: {$bankAccount->bank_name}");
                $this->line("  IBAN: {$bankAccount->iban}");
                $this->line("  Beneficiary: {$bankAccount->beneficiary_name}");
            } else {
                $this->line("  No bank account found");
            }
            $this->line("---");
        }
        
        // Test the summary calculation
        $billsWithBankAccounts = $bills->filter(function ($bill) {
            return $bill->provider?->bankAccounts?->isNotEmpty();
        });
        
        $this->info("Bills with provider bank accounts: {$billsWithBankAccounts->count()}");
        
        // Test the filter query
        $billsWithBankAccountsQuery = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('provider.bankAccounts')
            ->count();
        
        $this->info("Total unpaid bills with provider bank accounts: {$billsWithBankAccountsQuery}");
        
        // Test the accessor method for bank account details
        $this->info("\nTesting bank account accessor:");
        foreach ($bills as $bill) {
            $bankAccount = $bill->provider_bank_account;
            if ($bankAccount) {
                $this->line("- {$bill->name}: {$bankAccount->bank_name} - {$bankAccount->iban}");
            } else {
                $this->line("- {$bill->name}: No bank account");
            }
        }
        
        $this->info("\n✅ Provider IBAN display test completed successfully!");
    }
}
