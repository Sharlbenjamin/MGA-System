<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class CheckUncategorizedContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'branches:check-uncategorized';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for branches with uncategorized contacts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for branches with uncategorized contacts...');

        // Get all provider branches
        $branches = ProviderBranch::all();
        
        $branchesWithUncategorized = [];
        $totalContacts = 0;

        foreach ($branches as $branch) {
            // Check if branch has any contacts at all (through branch_id relationship)
            $contacts = Contact::where('branch_id', $branch->id)->get();

            if ($contacts->count() > 0) {
                $totalContacts += $contacts->count();
                
                // Check if any of these contacts are properly categorized
                $hasOperation = $branch->operationContact;
                $hasGOP = $branch->gopContact;
                $hasFinancial = $branch->financialContact;

                if (!$hasOperation && !$hasGOP && !$hasFinancial) {
                    $branchesWithUncategorized[] = [
                        'branch' => $branch,
                        'contacts' => $contacts
                    ];
                }
            }
        }

        $this->newLine();
        $this->info("📊 Uncategorized Contacts Summary:");
        $this->info("📈 Total branches: {$branches->count()}");
        $this->info("🔗 Branches with uncategorized contacts: " . count($branchesWithUncategorized));
        $this->info("📋 Total uncategorized contacts: {$totalContacts}");

        if (count($branchesWithUncategorized) > 0) {
            $this->newLine();
            $this->warn("⚠️  Branches with uncategorized contacts:");
            
            foreach ($branchesWithUncategorized as $item) {
                $branch = $item['branch'];
                $contacts = $item['contacts'];
                
                $this->line("   - {$branch->branch_name} (ID: {$branch->id})");
                $this->line("     📋 Has {$contacts->count()} contact(s) but none categorized:");
                
                foreach ($contacts as $contact) {
                    $this->line("       • {$contact->name} - {$contact->email} | {$contact->phone_number}");
                }
            }

            $this->newLine();
            $this->info("💡 To fix this, you need to:");
            $this->line("   1. Go to the admin panel");
            $this->line("   2. Edit each provider branch");
            $this->line("   3. Assign the contacts to the appropriate categories:");
            $this->line("      - Operation Contact");
            $this->line("      - GOP Contact");
            $this->line("      - Financial Contact");
            $this->line("   4. Then run: php artisan branches:fix-contacts");
        } else {
            $this->newLine();
            $this->info("✅ All branches with contacts have them properly categorized!");
        }

        // Also check for branches with no contacts at all
        $branchesWithNoContacts = $branches->filter(function($branch) {
            $contacts = Contact::where('branch_id', $branch->id)->count();
            return $contacts == 0;
        });

        if ($branchesWithNoContacts->count() > 0) {
            $this->newLine();
            $this->error("❌ Branches with NO contacts at all: {$branchesWithNoContacts->count()}");
            $this->line("   These branches need contacts to be created and assigned.");
        }

        return 0;
    }
}
