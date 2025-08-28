<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\ProviderBranch;

class CheckBranchContactDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:contact-details {--branch-id= : Check specific branch by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show detailed contact information for provider branches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $branchId = $this->option('branch-id');
        
        if ($branchId) {
            $this->checkSpecificBranch($branchId);
        } else {
            $this->checkAllBranches();
        }

        return 0;
    }

    /**
     * Check a specific branch
     */
    private function checkSpecificBranch($branchId)
    {
        $branch = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->find($branchId);
        
        if (!$branch) {
            $this->error("❌ Branch with ID {$branchId} not found!");
            return;
        }

        $this->info("🔍 Contact Details for: {$branch->branch_name} (ID: {$branch->id})");
        $this->newLine();

        // Get all contacts for this branch
        $branchContacts = Contact::where('branch_id', $branch->id)->get();
        
        $this->info("📋 All contacts assigned to this branch ({$branchContacts->count()}):");
        foreach ($branchContacts as $contact) {
            $this->line("   • {$contact->name} - {$contact->email} | {$contact->phone_number}");
        }

        $this->newLine();
        $this->info("🔗 Categorized contact relationships:");

        // Operation Contact
        if ($branch->operationContact) {
            $this->line("   ✅ Operation Contact: {$branch->operationContact->name} ({$branch->operationContact->email})");
        } else {
            $this->line("   ❌ No Operation Contact assigned");
        }

        // GOP Contact
        if ($branch->gopContact) {
            $this->line("   ✅ GOP Contact: {$branch->gopContact->name} ({$branch->gopContact->email})");
        } else {
            $this->line("   ❌ No GOP Contact assigned");
        }

        // Financial Contact
        if ($branch->financialContact) {
            $this->line("   ✅ Financial Contact: {$branch->financialContact->name} ({$branch->financialContact->email})");
        } else {
            $this->line("   ❌ No Financial Contact assigned");
        }

        $this->newLine();
        $this->info("📧 Direct contact fields:");
        $this->line("   Email: " . ($branch->email ?: 'Not set'));
        $this->line("   Phone: " . ($branch->phone ?: 'Not set'));
        $this->line("   Address: " . ($branch->address ?: 'Not set'));

        // Show uncategorized contacts
        $uncategorizedContacts = $branchContacts->filter(function($contact) use ($branch) {
            return $contact->id != ($branch->operation_contact_id ?? 0) &&
                   $contact->id != ($branch->gop_contact_id ?? 0) &&
                   $contact->id != ($branch->financial_contact_id ?? 0);
        });

        if ($uncategorizedContacts->count() > 0) {
            $this->newLine();
            $this->warn("⚠️  Uncategorized contacts ({$uncategorizedContacts->count()}):");
            foreach ($uncategorizedContacts as $contact) {
                $this->line("   • {$contact->name} - {$contact->email} | {$contact->phone_number}");
            }
        }
    }

    /**
     * Check all branches
     */
    private function checkAllBranches()
    {
        $this->info('🔍 Checking contact details for all provider branches...');
        $this->newLine();

        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $totalBranches = $branches->count();
        $branchesWithContacts = 0;
        $branchesWithCategorized = 0;
        $branchesWithUncategorized = 0;

        foreach ($branches as $branch) {
            $branchContacts = Contact::where('branch_id', $branch->id)->get();
            
            if ($branchContacts->count() > 0) {
                $branchesWithContacts++;
                
                $hasCategorized = $branch->operationContact || $branch->gopContact || $branch->financialContact;
                
                if ($hasCategorized) {
                    $branchesWithCategorized++;
                } else {
                    $branchesWithUncategorized++;
                }

                $this->line("🏢 {$branch->branch_name} (ID: {$branch->id})");
                $this->line("   📋 Total contacts: {$branchContacts->count()}");
                
                if ($hasCategorized) {
                    $this->line("   ✅ Has categorized contacts");
                    if ($branch->operationContact) {
                        $this->line("      📧 Operation: {$branch->operationContact->name}");
                    }
                    if ($branch->gopContact) {
                        $this->line("      📧 GOP: {$branch->gopContact->name}");
                    }
                    if ($branch->financialContact) {
                        $this->line("      📧 Financial: {$branch->financialContact->name}");
                    }
                } else {
                    $this->line("   ⚠️  Has uncategorized contacts:");
                    foreach ($branchContacts as $contact) {
                        $this->line("      • {$contact->name} - {$contact->email}");
                    }
                }
                
                $this->line("   📧 Direct email: " . ($branch->email ?: 'Not set'));
                $this->newLine();
            }
        }

        $this->info("📊 Summary:");
        $this->line("   📈 Total branches: {$totalBranches}");
        $this->line("   🔗 Branches with contacts: {$branchesWithContacts}");
        $this->line("   ✅ Branches with categorized contacts: {$branchesWithCategorized}");
        $this->line("   ⚠️  Branches with uncategorized contacts: {$branchesWithUncategorized}");
        $this->line("   ❌ Branches with no contacts: " . ($totalBranches - $branchesWithContacts));

        if ($branchesWithUncategorized > 0) {
            $this->newLine();
            $this->info("💡 To fix uncategorized contacts:");
            $this->line("   1. Run: php artisan branches:auto-categorize --dry-run");
            $this->line("   2. Run: php artisan branches:auto-categorize");
            $this->line("   3. Run: php artisan branches:fix-contacts");
        }
    }
}
