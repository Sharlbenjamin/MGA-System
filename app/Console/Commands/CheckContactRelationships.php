<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\Contact;

class CheckContactRelationships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:check-relationships';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check contact relationships for provider branches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking contact relationships for provider branches...');

        // Get all provider branches with their relationships
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $totalBranches = $branches->count();
        $withOperation = $branches->filter(fn($b) => $b->operationContact)->count();
        $withGOP = $branches->filter(fn($b) => $b->gopContact)->count();
        $withFinancial = $branches->filter(fn($b) => $b->financialContact)->count();
        $withAnyContact = $branches->filter(fn($b) => $b->operationContact || $b->gopContact || $b->financialContact)->count();

        $this->newLine();
        $this->info("📊 Contact Relationship Summary:");
        $this->info("📈 Total branches: {$totalBranches}");
        $this->info("🔗 Branches with Operation Contact: {$withOperation}");
        $this->info("🔗 Branches with GOP Contact: {$withGOP}");
        $this->info("🔗 Branches with Financial Contact: {$withFinancial}");
        $this->info("🔗 Branches with ANY contact: {$withAnyContact}");
        $this->info("❌ Branches with NO contacts: " . ($totalBranches - $withAnyContact));

        // Check total contacts in the system
        $totalContacts = Contact::count();
        $this->newLine();
        $this->info("📋 Total contacts in system: {$totalContacts}");

        // Show branches with contacts
        if ($withAnyContact > 0) {
            $this->newLine();
            $this->info("✅ Branches WITH contact relationships:");
            foreach ($branches as $branch) {
                if ($branch->operationContact || $branch->gopContact || $branch->financialContact) {
                    $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
                    if ($branch->operationContact) {
                        $this->line("     📧 Operation: {$branch->operationContact->email} | 📞 {$branch->operationContact->phone_number}");
                    }
                    if ($branch->gopContact) {
                        $this->line("     📧 GOP: {$branch->gopContact->email} | 📞 {$branch->gopContact->phone_number}");
                    }
                    if ($branch->financialContact) {
                        $this->line("     📧 Financial: {$branch->financialContact->email} | 📞 {$branch->financialContact->phone_number}");
                    }
                }
            }
        }

        // Show sample branches without contacts
        if ($totalBranches - $withAnyContact > 0) {
            $this->newLine();
            $this->warn("❌ Sample branches WITHOUT contact relationships:");
            $branchesWithoutContacts = $branches->filter(fn($b) => !$b->operationContact && !$b->gopContact && !$b->financialContact)->take(5);
            foreach ($branchesWithoutContacts as $branch) {
                $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
            }
            if ($totalBranches - $withAnyContact > 5) {
                $this->line("   ... and " . ($totalBranches - $withAnyContact - 5) . " more");
            }
        }

        // Check if there are any contacts at all
        if ($totalContacts == 0) {
            $this->newLine();
            $this->error("🚨 CRITICAL: No contacts exist in the system!");
            $this->info("💡 You need to create contacts first before they can be assigned to branches.");
        } elseif ($withAnyContact == 0) {
            $this->newLine();
            $this->warn("⚠️  WARNING: No branches have contact relationships assigned!");
            $this->info("💡 You need to assign contacts to branches in the admin panel.");
        }

        $this->newLine();
        $this->info("💡 Next steps:");
        if ($totalContacts == 0) {
            $this->line("   1. Create contacts in the admin panel");
            $this->line("   2. Assign contacts to provider branches");
        } elseif ($withAnyContact == 0) {
            $this->line("   1. Assign existing contacts to provider branches");
            $this->line("   2. Run: php artisan branches:fix-contacts");
        } else {
            $this->line("   1. Run: php artisan branches:fix-contacts");
            $this->line("   2. Assign contacts to remaining branches");
        }

        return 0;
    }
}
