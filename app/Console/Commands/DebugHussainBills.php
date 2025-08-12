<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class DebugHussainBills extends Command
{
    protected $signature = 'debug:hussain-bills';
    protected $description = 'Debug Dr. Hussain bills and their due dates';

    public function handle()
    {
        $this->info('=== Debugging Dr. Hussain Bills ===');
        
        // Get all Dr. Hussain bills
        $hussainBills = Bill::whereIn('status', ['Unpaid', 'Partial'])
            ->whereHas('provider', function ($query) {
                $query->where('name', 'like', '%Hussain%');
            })
            ->with(['provider', 'branch', 'file'])
            ->orderBy('due_date', 'asc')
            ->get();
        
        $this->info("Found {$hussainBills->count()} Dr. Hussain bills");
        
        // Group by due date to see the pattern
        $groupedByDate = $hussainBills->groupBy('due_date');
        
        foreach ($groupedByDate as $dueDate => $bills) {
            $this->line("\n=== Due Date: {$dueDate} ===");
            $this->line("Bills count: {$bills->count()}");
            
            foreach ($bills as $bill) {
                $this->line("  - {$bill->name} (ID: {$bill->id})");
            }
        }
        
        // Check if there are any null due dates
        $nullDueDateBills = $hussainBills->whereNull('due_date');
        if ($nullDueDateBills->count() > 0) {
            $this->line("\n=== Bills with NULL due_date ===");
            foreach ($nullDueDateBills as $bill) {
                $this->line("  - {$bill->name} (ID: {$bill->id})");
            }
        }
        
        // Check provider consistency
        $providers = $hussainBills->pluck('provider')->unique('id');
        $this->line("\n=== Providers found ===");
        foreach ($providers as $provider) {
            $this->line("Provider ID: {$provider->id}, Name: {$provider->name}");
        }
        
        $this->info("\n=== Debug Complete ===");
    }
}
