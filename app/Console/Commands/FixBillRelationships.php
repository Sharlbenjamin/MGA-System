<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class FixBillRelationships extends Command
{
    protected $signature = 'fix:bill-relationships';
    protected $description = 'Fix bills with missing provider or branch relationships';

    public function handle()
    {
        $this->info('=== Fixing Bill Relationships ===');
        
        // Get all unpaid/partial bills
        $bills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->with(['file.providerBranch.provider'])
            ->get();
        
        $this->info("Total unpaid/partial bills: {$bills->count()}");
        
        $fixedCount = 0;
        
        foreach ($bills as $bill) {
            $updated = false;
            
            // Fix provider_id if missing
            if (!$bill->provider_id && $bill->file && $bill->file->providerBranch && $bill->file->providerBranch->provider) {
                $bill->provider_id = $bill->file->providerBranch->provider_id;
                $updated = true;
                $this->line("Fixed provider_id for bill {$bill->name}: {$bill->provider_id}");
            }
            
            // Fix branch_id if missing
            if (!$bill->branch_id && $bill->file && $bill->file->provider_branch_id) {
                $bill->branch_id = $bill->file->provider_branch_id;
                $updated = true;
                $this->line("Fixed branch_id for bill {$bill->name}: {$bill->branch_id}");
            }
            
            if ($updated) {
                $bill->save();
                $fixedCount++;
            }
        }
        
        $this->info("Fixed {$fixedCount} bills");
        
        // Verify the fix
        $this->info("\n=== Verification ===");
        $billsWithNullProvider = Bill::whereIn('status', ['Unpaid', 'Partial'])->whereNull('provider_id')->count();
        $billsWithNullBranch = Bill::whereIn('status', ['Unpaid', 'Partial'])->whereNull('branch_id')->count();
        
        $this->line("Bills with null provider_id: {$billsWithNullProvider}");
        $this->line("Bills with null branch_id: {$billsWithNullBranch}");
        
        if ($billsWithNullProvider === 0 && $billsWithNullBranch === 0) {
            $this->info("✅ All bills now have proper relationships!");
        } else {
            $this->warn("⚠️ Some bills still have null relationships");
        }
        
        $this->info("\n=== Fix Complete ===");
    }
}
