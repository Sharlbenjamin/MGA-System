<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use Illuminate\Support\Facades\DB;

class FixBranchContactData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:fix-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix branch contact data by copying from relationships to direct fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Starting to fix branch contact data...');

        // Get all provider branches with their relationships
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $updatedCount = 0;
        $skippedCount = 0;

        $this->info("Found {$branches->count()} provider branches to check...");

        foreach ($branches as $branch) {
            $updated = false;
            $updates = [];

            // Check if direct fields are empty but relationships have data
            if (empty($branch->email)) {
                $email = $this->getPriorityEmail($branch);
                if ($email) {
                    $updates['email'] = $email;
                    $updated = true;
                }
            }

            if (empty($branch->phone)) {
                $phone = $this->getPriorityPhone($branch);
                if ($phone) {
                    $updates['phone'] = $phone;
                    $updated = true;
                }
            }

            if (empty($branch->address)) {
                $address = $this->getPriorityAddress($branch);
                if ($address) {
                    $updates['address'] = $address;
                    $updated = true;
                }
            }

            if ($updated) {
                try {
                    $branch->update($updates);
                    $updatedCount++;
                    
                    $this->line("✅ Updated branch: {$branch->branch_name}");
                    if (isset($updates['email'])) {
                        $this->line("   📧 Email: {$updates['email']}");
                    }
                    if (isset($updates['phone'])) {
                        $this->line("   📞 Phone: {$updates['phone']}");
                    }
                    if (isset($updates['address'])) {
                        $this->line("   📍 Address: {$updates['address']}");
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Failed to update branch {$branch->branch_name}: " . $e->getMessage());
                }
            } else {
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("🎉 Contact data fix completed!");
        $this->info("✅ Updated: {$updatedCount} branches");
        $this->info("⏭️  Skipped: {$skippedCount} branches (already have data or no contact data)");

        // Show summary of branches that still need attention
        $this->newLine();
        $this->info("📊 Summary of branches that still need attention:");
        
        $branchesWithNoContacts = ProviderBranch::where(function($query) {
            $query->whereNull('email')->orWhere('email', '')
                  ->whereNull('phone')->orWhere('phone', '')
                  ->whereNull('address')->orWhere('address', '');
        })->whereDoesntHave('operationContact', function($q) {
            $q->whereNotNull('email')->orWhereNotNull('phone_number');
        })->whereDoesntHave('gopContact', function($q) {
            $q->whereNotNull('email')->orWhereNotNull('phone_number');
        })->whereDoesntHave('financialContact', function($q) {
            $q->whereNotNull('email')->orWhereNotNull('phone_number');
        })->get();

        if ($branchesWithNoContacts->count() > 0) {
            $this->warn("⚠️  {$branchesWithNoContacts->count()} branches have no contact data at all:");
            foreach ($branchesWithNoContacts as $branch) {
                $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
            }
        } else {
            $this->info("✅ All branches now have contact data!");
        }

        return 0;
    }

    /**
     * Get priority email based on contact hierarchy
     */
    private function getPriorityEmail($branch)
    {
        // Priority: Operation Contact > GOP Contact > Financial Contact
        if ($branch->operationContact && !empty($branch->operationContact->email)) {
            return $branch->operationContact->email;
        }
        
        if ($branch->gopContact && !empty($branch->gopContact->email)) {
            return $branch->gopContact->email;
        }
        
        if ($branch->financialContact && !empty($branch->financialContact->email)) {
            return $branch->financialContact->email;
        }
        
        return null;
    }

    /**
     * Get priority phone based on contact hierarchy
     */
    private function getPriorityPhone($branch)
    {
        // Priority: Operation Contact > GOP Contact > Financial Contact
        if ($branch->operationContact && !empty($branch->operationContact->phone_number)) {
            return $branch->operationContact->phone_number;
        }
        
        if ($branch->gopContact && !empty($branch->gopContact->phone_number)) {
            return $branch->gopContact->phone_number;
        }
        
        if ($branch->financialContact && !empty($branch->financialContact->phone_number)) {
            return $branch->financialContact->phone_number;
        }
        
        return null;
    }

    /**
     * Get priority address based on contact hierarchy
     */
    private function getPriorityAddress($branch)
    {
        // Priority: Operation Contact > GOP Contact > Financial Contact
        if ($branch->operationContact && !empty($branch->operationContact->address)) {
            return $branch->operationContact->address;
        }
        
        if ($branch->gopContact && !empty($branch->gopContact->address)) {
            return $branch->gopContact->address;
        }
        
        if ($branch->financialContact && !empty($branch->financialContact->address)) {
            return $branch->financialContact->address;
        }
        
        return null;
    }
}
