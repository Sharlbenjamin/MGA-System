<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\ProviderBranch;
use Illuminate\Support\Facades\DB;

class FixAllContactIssues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:fix-all {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fix all contact categorization and data issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        
        $this->info('🚀 Starting comprehensive contact fix process...');
        $this->newLine();

        // Step 1: Analyze current state
        $this->info('📊 Step 1: Analyzing current contact state...');
        $this->analyzeCurrentState();
        $this->newLine();

        if (!$force) {
            if (!$this->confirm('Do you want to proceed with fixing all contact issues?')) {
                $this->info('❌ Operation cancelled.');
                return 0;
            }
        }

        // Step 2: Auto-categorize contacts
        $this->info('🔧 Step 2: Auto-categorizing contacts...');
        $this->autoCategorizeContacts();
        $this->newLine();

        // Step 3: Copy data to direct fields
        $this->info('📋 Step 3: Copying contact data to direct fields...');
        $this->copyContactDataToDirectFields();
        $this->newLine();

        // Step 4: Final verification
        $this->info('✅ Step 4: Final verification...');
        $this->finalVerification();
        $this->newLine();

        $this->info('🎉 All contact issues have been automatically fixed!');
        $this->info('💡 The Request Appointments page should now work correctly.');

        return 0;
    }

    /**
     * Analyze current state
     */
    private function analyzeCurrentState()
    {
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        $totalBranches = $branches->count();
        
        $branchesWithContacts = 0;
        $branchesWithCategorized = 0;
        $branchesWithDirectData = 0;

        foreach ($branches as $branch) {
            $branchContacts = Contact::where('branch_id', $branch->id)->get();
            
            if ($branchContacts->count() > 0) {
                $branchesWithContacts++;
                
                if ($branch->operationContact || $branch->gopContact || $branch->financialContact) {
                    $branchesWithCategorized++;
                }
                
                if (!empty($branch->email) || !empty($branch->phone) || !empty($branch->address)) {
                    $branchesWithDirectData++;
                }
            }
        }

        $this->line("   📈 Total branches: {$totalBranches}");
        $this->line("   🔗 Branches with contacts: {$branchesWithContacts}");
        $this->line("   ✅ Branches with categorized contacts: {$branchesWithCategorized}");
        $this->line("   📧 Branches with direct contact data: {$branchesWithDirectData}");
        $this->line("   ❌ Branches needing fixes: " . ($branchesWithContacts - $branchesWithCategorized));
    }

    /**
     * Auto-categorize contacts
     */
    private function autoCategorizeContacts()
    {
        $branches = ProviderBranch::all();
        $categorizedCount = 0;

        foreach ($branches as $branch) {
            $contacts = Contact::where('branch_id', $branch->id)->get();
            
            if ($contacts->count() > 0) {
                $hasCategorized = $branch->operationContact || $branch->gopContact || $branch->financialContact;
                
                if (!$hasCategorized) {
                    $updates = [];
                    
                    foreach ($contacts as $index => $contact) {
                        $contactType = $this->determineContactType($contact, $branch, $index);
                        $updates[$contactType] = $contact->id;
                    }
                    
                    if (!empty($updates)) {
                        try {
                            $branch->update($updates);
                            $categorizedCount++;
                            $this->line("   ✅ Categorized contacts for: {$branch->branch_name}");
                        } catch (\Exception $e) {
                            $this->error("   ❌ Failed to categorize contacts for: {$branch->branch_name}");
                        }
                    }
                }
            }
        }

        $this->line("   🎯 Categorized contacts for {$categorizedCount} branches");
    }

    /**
     * Copy contact data to direct fields
     */
    private function copyContactDataToDirectFields()
    {
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        $updatedCount = 0;

        foreach ($branches as $branch) {
            $updates = [];
            $updated = false;

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
                    $this->line("   ✅ Updated direct fields for: {$branch->branch_name}");
                } catch (\Exception $e) {
                    $this->error("   ❌ Failed to update direct fields for: {$branch->branch_name}");
                }
            }
        }

        $this->line("   🎯 Updated direct fields for {$updatedCount} branches");
    }

    /**
     * Final verification
     */
    private function finalVerification()
    {
        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $branchesWithDirectData = 0;
        $branchesWithCategorized = 0;

        foreach ($branches as $branch) {
            if (!empty($branch->email) || !empty($branch->phone) || !empty($branch->address)) {
                $branchesWithDirectData++;
            }
            
            if ($branch->operationContact || $branch->gopContact || $branch->financialContact) {
                $branchesWithCategorized++;
            }
        }

        $this->line("   ✅ Branches with direct contact data: {$branchesWithDirectData}");
        $this->line("   ✅ Branches with categorized contacts: {$branchesWithCategorized}");
        $this->line("   📊 Total branches: {$branches->count()}");
    }

    /**
     * Determine contact type
     */
    private function determineContactType($contact, $branch, $index)
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

        // If multiple contacts, assign based on order
        switch ($index) {
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

    /**
     * Get priority email
     */
    private function getPriorityEmail($branch)
    {
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
     * Get priority phone
     */
    private function getPriorityPhone($branch)
    {
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
     * Get priority address
     */
    private function getPriorityAddress($branch)
    {
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
