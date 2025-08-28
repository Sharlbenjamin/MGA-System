<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class AutoCategorizeContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:auto-categorize {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically categorize uncategorized contacts for provider branches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN: Checking what would be categorized...');
        } else {
            $this->info('🔧 Auto-categorizing contacts for provider branches...');
        }

        // Get all provider branches
        $branches = ProviderBranch::all();
        
        $categorizedCount = 0;
        $skippedCount = 0;

        foreach ($branches as $branch) {
            // Check if branch has any contacts at all
            $contacts = Contact::where('branch_id', $branch->id)->get();

            if ($contacts->count() > 0) {
                // Check if any of these contacts are properly categorized
                $hasOperation = $branch->operationContact;
                $hasGOP = $branch->gopContact;
                $hasFinancial = $branch->financialContact;

                if (!$hasOperation && !$hasGOP && !$hasFinancial) {
                    // Auto-categorize based on contact type or other criteria
                    $categorized = $this->categorizeContacts($branch, $contacts, $isDryRun);
                    
                    if ($categorized) {
                        $categorizedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->info("📊 DRY RUN Results:");
            $this->info("🔧 Would categorize: {$categorizedCount} branches");
            $this->info("⏭️  Would skip: {$skippedCount} branches");
            $this->info("💡 Run without --dry-run to actually make the changes");
        } else {
            $this->info("🎉 Auto-categorization completed!");
            $this->info("✅ Categorized: {$categorizedCount} branches");
            $this->info("⏭️  Skipped: {$skippedCount} branches");
            $this->info("💡 Now run: php artisan branches:fix-contacts");
        }

        return 0;
    }

    /**
     * Categorize contacts for a branch
     */
    private function categorizeContacts($branch, $contacts, $isDryRun)
    {
        $updates = [];
        $categorized = false;

        foreach ($contacts as $contact) {
            // Determine contact type based on various criteria
            $contactType = $this->determineContactType($contact, $branch);
            
            if ($contactType) {
                $updates[$contactType] = $contact->id;
                $categorized = true;
                
                if ($isDryRun) {
                    $this->line("   Would assign: {$contact->name} as {$contactType} for {$branch->branch_name}");
                } else {
                    $this->line("   ✅ Assigned: {$contact->name} as {$contactType} for {$branch->branch_name}");
                }
            }
        }

        if ($categorized && !$isDryRun) {
            try {
                $branch->update($updates);
            } catch (\Exception $e) {
                $this->error("❌ Failed to update branch {$branch->branch_name}: " . $e->getMessage());
                return false;
            }
        }

        return $categorized;
    }

    /**
     * Determine the type of contact based on various criteria
     */
    private function determineContactType($contact, $branch)
    {
        // Check if contact already has a preferred_contact_type
        if ($contact->preferred_contact_type) {
            switch (strtolower($contact->preferred_contact_type)) {
                case 'operation':
                case 'operational':
                    return 'operation_contact_id';
                case 'gop':
                case 'guarantee of payment':
                    return 'gop_contact_id';
                case 'financial':
                case 'finance':
                    return 'financial_contact_id';
            }
        }

        // Check contact name for keywords
        $name = strtolower($contact->name);
        $email = strtolower($contact->email ?? '');
        
        // Operation contact keywords
        if (str_contains($name, 'operation') || str_contains($name, 'admin') || 
            str_contains($name, 'manager') || str_contains($name, 'coordinator') ||
            str_contains($name, 'director') || str_contains($name, 'head') ||
            str_contains($email, 'operation') || str_contains($email, 'admin') ||
            str_contains($email, 'manager') || str_contains($email, 'director')) {
            return 'operation_contact_id';
        }
        
        // GOP contact keywords
        if (str_contains($name, 'gop') || str_contains($name, 'guarantee') || 
            str_contains($name, 'payment') || str_contains($name, 'billing') ||
            str_contains($name, 'accounts') || str_contains($name, 'revenue') ||
            str_contains($email, 'gop') || str_contains($email, 'payment') ||
            str_contains($email, 'billing') || str_contains($email, 'accounts')) {
            return 'gop_contact_id';
        }
        
        // Financial contact keywords
        if (str_contains($name, 'financial') || str_contains($name, 'finance') || 
            str_contains($name, 'account') || str_contains($name, 'billing') ||
            str_contains($name, 'treasurer') || str_contains($name, 'controller') ||
            str_contains($email, 'financial') || str_contains($email, 'finance') ||
            str_contains($email, 'accounting') || str_contains($email, 'treasurer')) {
            return 'financial_contact_id';
        }

        // If only one contact, assign as operation contact
        $totalContacts = Contact::where('branch_id', $branch->id)->count();
        if ($totalContacts == 1) {
            return 'operation_contact_id';
        }

        // If multiple contacts, assign based on order (first = operation, second = GOP, third = financial)
        $contactOrder = Contact::where('branch_id', $branch->id)->orderBy('created_at')->pluck('id')->toArray();
        $contactIndex = array_search($contact->id, $contactOrder);
        
        switch ($contactIndex) {
            case 0:
                return 'operation_contact_id';
            case 1:
                return 'gop_contact_id';
            case 2:
                return 'financial_contact_id';
            default:
                return 'operation_contact_id';
        }
    }
}
