<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;

class CheckBranchContactStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of branch contact data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('📊 Checking branch contact data status...');

        // Get all provider branches with their relationships
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $needsFixing = [];
        $hasData = [];
        $noContacts = [];

        foreach ($branches as $branch) {
            $hasDirectData = !empty($branch->email) || !empty($branch->phone) || !empty($branch->address);
            $hasRelationshipData = ($branch->operationContact && (!empty($branch->operationContact->email) || !empty($branch->operationContact->phone_number))) ||
                                  ($branch->gopContact && (!empty($branch->gopContact->email) || !empty($branch->gopContact->phone_number))) ||
                                  ($branch->financialContact && (!empty($branch->financialContact->email) || !empty($branch->financialContact->phone_number)));

            if (!$hasDirectData && $hasRelationshipData) {
                $needsFixing[] = $branch;
            } elseif ($hasDirectData) {
                $hasData[] = $branch;
            } else {
                $noContacts[] = $branch;
            }
        }

        $this->newLine();
        $this->info("📈 Contact Data Status Summary:");
        $this->info("✅ Branches with direct contact data: " . count($hasData));
        $this->info("🔧 Branches that need fixing: " . count($needsFixing));
        $this->info("❌ Branches with no contact data: " . count($noContacts));
        $this->info("📊 Total branches: " . $branches->count());

        if (count($needsFixing) > 0) {
            $this->newLine();
            $this->warn("🔧 Branches that need fixing (have relationship data but no direct data):");
            foreach ($needsFixing as $branch) {
                $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
                
                // Show what relationship data is available
                if ($branch->operationContact) {
                    $this->line("     📧 Operation Contact: " . ($branch->operationContact->email ?: 'No email') . 
                               " | 📞 " . ($branch->operationContact->phone_number ?: 'No phone'));
                }
                if ($branch->gopContact) {
                    $this->line("     📧 GOP Contact: " . ($branch->gopContact->email ?: 'No email') . 
                               " | 📞 " . ($branch->gopContact->phone_number ?: 'No phone'));
                }
                if ($branch->financialContact) {
                    $this->line("     📧 Financial Contact: " . ($branch->financialContact->email ?: 'No email') . 
                               " | 📞 " . ($branch->financialContact->phone_number ?: 'No phone'));
                }
            }
        }

        if (count($noContacts) > 0) {
            $this->newLine();
            $this->error("❌ Branches with no contact data at all:");
            foreach ($noContacts as $branch) {
                $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
            }
        }

        if (count($needsFixing) > 0) {
            $this->newLine();
            $this->info("💡 To fix the branches that need attention, run:");
            $this->line("   php artisan branches:fix-contacts");
        }

        return 0;
    }
}
