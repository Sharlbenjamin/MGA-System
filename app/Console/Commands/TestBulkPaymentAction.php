<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Transaction;
use Illuminate\Console\Command;

class TestBulkPaymentAction extends Command
{
    protected $signature = 'test:bulk-payment-action';
    protected $description = 'Test the bulk payment action functionality';

    public function handle()
    {
        $this->info('Testing bulk payment action...');
        
        // Get some unpaid bills for testing
        $unpaidBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['provider', 'branch', 'file'])
            ->take(3)
            ->get();
        
        if ($unpaidBills->isEmpty()) {
            $this->warn('No unpaid bills found for testing!');
            return;
        }
        
        $this->info("Found {$unpaidBills->count()} unpaid bills for testing");
        
        // Display the bills
        foreach ($unpaidBills as $bill) {
            $this->line("- Bill: {$bill->name}");
            $this->line("  Provider: " . ($bill->provider ? $bill->provider->name : 'No Provider'));
            $this->line("  Branch: " . ($bill->branch ? $bill->branch->branch_name : 'No Branch'));
            $this->line("  File: " . ($bill->file ? $bill->file->mga_reference : 'No File'));
            $this->line("  Amount: €" . number_format($bill->total_amount - $bill->paid_amount, 2));
            $this->line("---");
        }
        
        // Test the transaction name generation
        $billIds = $unpaidBills->pluck('id');
        $bills = Bill::whereIn('id', $billIds)
            ->with('file')
            ->get();
        
        $fileReferences = $bills->pluck('file.mga_reference')
            ->filter()
            ->unique()
            ->take(3);
        
        $references = $fileReferences->implode(', ');
        if ($fileReferences->count() > 3) {
            $references .= ' and ' . ($fileReferences->count() - 3) . ' more';
        }
        
        $transactionName = 'Transaction on ' . now()->format('Y-m-d') . ' for ' . $references;
        $this->info("Generated transaction name: {$transactionName}");
        
        // Test the amount calculation
        $totalAmount = $bills->sum(function ($bill) {
            return $bill->total_amount - $bill->paid_amount;
        });
        $this->info("Total amount: €" . number_format($totalAmount, 2));
        
        // Test provider/branch options
        $providers = $bills->pluck('provider')
            ->filter()
            ->unique('id')
            ->pluck('name', 'id');
        
        $branches = $bills->pluck('branch')
            ->filter()
            ->unique('id')
            ->pluck('branch_name', 'id');
        
        $this->info("Available providers: " . $providers->count());
        $this->info("Available branches: " . $branches->count());
        
        // Test the attachBillsForDraft method
        $this->info("\nTesting attachBillsForDraft method...");
        
        try {
            // Create a test transaction
            $transaction = new Transaction();
            $transaction->type = 'Outflow';
            $transaction->related_type = 'Provider';
            $transaction->related_id = $providers->keys()->first();
            $transaction->name = $transactionName;
            $transaction->amount = $totalAmount;
            $transaction->date = now();
            $transaction->status = 'Draft';
            $transaction->save();
            
            $this->info("Created test transaction: {$transaction->name}");
            
            // Attach bills using the new method
            $transaction->attachBillsForDraft($billIds->toArray());
            
            $this->info("Attached {$transaction->bills()->count()} bills to transaction");
            
            // Check that bills are not marked as paid
            $attachedBills = $transaction->bills;
            foreach ($attachedBills as $bill) {
                $this->line("- Bill: {$bill->name}, Status: {$bill->status}, Paid: €{$bill->paid_amount}");
            }
            
            // Test finalization
            $this->info("\nTesting finalizeTransaction method...");
            $transaction->finalizeTransaction();
            
            $this->info("Transaction finalized. New status: {$transaction->status}");
            
            // Check that bills are now marked as paid
            $finalizedBills = $transaction->bills;
            foreach ($finalizedBills as $bill) {
                $this->line("- Bill: {$bill->name}, Status: {$bill->status}, Paid: €{$bill->paid_amount}");
            }
            
            // Clean up - delete the test transaction
            $transaction->delete();
            $this->info("Test transaction cleaned up");
            
        } catch (\Exception $e) {
            $this->error("Error during testing: " . $e->getMessage());
        }
        
        $this->info("\n✅ Bulk payment action test completed successfully!");
    }
}
