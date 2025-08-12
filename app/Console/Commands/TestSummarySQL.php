<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestSummarySQL extends Command
{
    protected $signature = 'test:summary-sql';
    protected $description = 'Test the SQL summary query for remaining amount';

    public function handle()
    {
        $this->info('Testing SQL summary query...');
        
        try {
            // Test the exact query that will be used in the summary
            $result = Bill::whereIn('status', ['Unpaid', 'Partial'])
                ->sum(DB::raw('total_amount - paid_amount'));
            
            $this->info("SQL query result: €" . number_format($result, 2));
            
            // Also test the individual sums
            $totalAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])->sum('total_amount');
            $paidAmount = Bill::whereIn('status', ['Unpaid', 'Partial'])->sum('paid_amount');
            $calculatedResult = $totalAmount - $paidAmount;
            
            $this->info("Total Amount: €" . number_format($totalAmount, 2));
            $this->info("Paid Amount: €" . number_format($paidAmount, 2));
            $this->info("Calculated Result: €" . number_format($calculatedResult, 2));
            
            if (abs($result - $calculatedResult) < 0.01) {
                $this->info("✅ SQL query is working correctly!");
            } else {
                $this->warn("⚠️ SQL query result differs from calculation!");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ SQL query failed: " . $e->getMessage());
        }
    }
}
