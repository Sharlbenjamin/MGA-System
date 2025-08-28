<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Client;
use App\Models\Patient;

class AnalyzeContactAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:analyze {--detailed : Show detailed information for each contact}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze all contacts and their assignments to providers, branches, clients, and patients';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDetailed = $this->option('detailed');
        
        $this->info('🔍 Analyzing contact assignments...');
        $this->newLine();

        // Get all contacts with their relationships
        $contacts = Contact::with(['client', 'provider', 'branch', 'patient'])->get();
        
        $totalContacts = $contacts->count();
        $this->info("📊 Total contacts in system: {$totalContacts}");
        $this->newLine();

        // Analyze by type
        $this->analyzeByType($contacts);
        $this->newLine();

        // Analyze assignments
        $this->analyzeAssignments($contacts, $isDetailed);
        $this->newLine();

        // Analyze provider branch contact relationships
        $this->analyzeProviderBranchContacts();
        $this->newLine();

        // Show unassigned contacts
        $this->showUnassignedContacts($contacts);
        $this->newLine();

        // Show summary
        $this->showSummary($contacts);

        return 0;
    }

    /**
     * Analyze contacts by type
     */
    private function analyzeByType($contacts)
    {
        $this->info('📋 Contact Analysis by Type:');
        
        $types = $contacts->groupBy('type');
        
        foreach ($types as $type => $typeContacts) {
            $this->line("   {$type}: {$typeContacts->count()} contacts");
        }
    }

    /**
     * Analyze contact assignments
     */
    private function analyzeAssignments($contacts, $isDetailed)
    {
        $this->info('🔗 Contact Assignment Analysis:');

        // Client contacts
        $clientContacts = $contacts->whereNotNull('client_id');
        $this->line("   📧 Client Contacts: {$clientContacts->count()}");
        if ($isDetailed && $clientContacts->count() > 0) {
            foreach ($clientContacts as $contact) {
                $client = $contact->client;
                $this->line("     • {$contact->name} → {$client->name} (ID: {$client->id})");
            }
        }

        // Provider contacts
        $providerContacts = $contacts->whereNotNull('provider_id');
        $this->line("   🏥 Provider Contacts: {$providerContacts->count()}");
        if ($isDetailed && $providerContacts->count() > 0) {
            foreach ($providerContacts as $contact) {
                $provider = $contact->provider;
                $this->line("     • {$contact->name} → {$provider->name} (ID: {$provider->id})");
            }
        }

        // Branch contacts
        $branchContacts = $contacts->whereNotNull('branch_id');
        $this->line("   🏢 Branch Contacts: {$branchContacts->count()}");
        if ($isDetailed && $branchContacts->count() > 0) {
            foreach ($branchContacts as $contact) {
                $branch = $contact->branch;
                $provider = $branch ? $branch->provider : null;
                $providerName = $provider ? $provider->name : 'Unknown Provider';
                $this->line("     • {$contact->name} → {$branch->branch_name} (Provider: {$providerName})");
            }
        }

        // Patient contacts
        $patientContacts = $contacts->whereNotNull('patient_id');
        $this->line("   👤 Patient Contacts: {$patientContacts->count()}");
        if ($isDetailed && $patientContacts->count() > 0) {
            foreach ($patientContacts as $contact) {
                $patient = $contact->patient;
                $this->line("     • {$contact->name} → {$patient->name} (ID: {$patient->id})");
            }
        }

        // Unassigned contacts
        $unassignedContacts = $contacts->whereNull('client_id')
                                      ->whereNull('provider_id')
                                      ->whereNull('branch_id')
                                      ->whereNull('patient_id');
        $this->line("   ❓ Unassigned Contacts: {$unassignedContacts->count()}");
        if ($isDetailed && $unassignedContacts->count() > 0) {
            foreach ($unassignedContacts as $contact) {
                $this->line("     • {$contact->name} ({$contact->email}) - Type: {$contact->type}");
            }
        }
    }

    /**
     * Analyze provider branch contact relationships
     */
    private function analyzeProviderBranchContacts()
    {
        $this->info('🏥 Provider Branch Contact Relationship Analysis:');

        $branches = ProviderBranch::with(['operationContact', 'gopContact', 'financialContact'])->get();
        
        $withOperation = $branches->filter(fn($b) => $b->operationContact)->count();
        $withGOP = $branches->filter(fn($b) => $b->gopContact)->count();
        $withFinancial = $branches->filter(fn($b) => $b->financialContact)->count();
        $withAnyContact = $branches->filter(fn($b) => $b->operationContact || $b->gopContact || $b->financialContact)->count();

        $this->line("   📈 Total Provider Branches: {$branches->count()}");
        $this->line("   🔗 Branches with Operation Contact: {$withOperation}");
        $this->line("   🔗 Branches with GOP Contact: {$withGOP}");
        $this->line("   🔗 Branches with Financial Contact: {$withFinancial}");
        $this->line("   🔗 Branches with ANY categorized contact: {$withAnyContact}");
        $this->line("   ❌ Branches with NO categorized contacts: " . ($branches->count() - $withAnyContact));

        // Show branches with categorized contacts
        if ($withAnyContact > 0) {
            $this->newLine();
            $this->line("   ✅ Branches with categorized contacts:");
            foreach ($branches as $branch) {
                if ($branch->operationContact || $branch->gopContact || $branch->financialContact) {
                    $this->line("     • {$branch->branch_name} (ID: {$branch->id})");
                    if ($branch->operationContact) {
                        $this->line("       📧 Operation: {$branch->operationContact->name} ({$branch->operationContact->email})");
                    }
                    if ($branch->gopContact) {
                        $this->line("       📧 GOP: {$branch->gopContact->name} ({$branch->gopContact->email})");
                    }
                    if ($branch->financialContact) {
                        $this->line("       📧 Financial: {$branch->financialContact->name} ({$branch->financialContact->email})");
                    }
                }
            }
        }
    }

    /**
     * Show unassigned contacts
     */
    private function showUnassignedContacts($contacts)
    {
        $unassigned = $contacts->whereNull('client_id')
                               ->whereNull('provider_id')
                               ->whereNull('branch_id')
                               ->whereNull('patient_id');

        if ($unassigned->count() > 0) {
            $this->warn("⚠️  Unassigned Contacts ({$unassigned->count()}):");
            foreach ($unassigned as $contact) {
                $this->line("   • {$contact->name} - {$contact->email} - Type: {$contact->type}");
            }
        } else {
            $this->info("✅ All contacts are properly assigned!");
        }
    }

    /**
     * Show summary
     */
    private function showSummary($contacts)
    {
        $this->info('📊 Summary:');
        
        $totalContacts = $contacts->count();
        $assignedContacts = $contacts->filter(function($contact) {
            return $contact->client_id || $contact->provider_id || $contact->branch_id || $contact->patient_id;
        })->count();
        
        $this->line("   📈 Total contacts: {$totalContacts}");
        $this->line("   ✅ Assigned contacts: {$assignedContacts}");
        $this->line("   ❌ Unassigned contacts: " . ($totalContacts - $assignedContacts));
        
        if ($totalContacts > 0) {
            $assignmentRate = round(($assignedContacts / $totalContacts) * 100, 1);
            $this->line("   📊 Assignment rate: {$assignmentRate}%");
        }

        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line("   1. Run: php artisan branches:check-uncategorized");
        $this->line("   2. Run: php artisan branches:auto-categorize --dry-run");
        $this->line("   3. Run: php artisan branches:auto-categorize");
        $this->line("   4. Run: php artisan branches:fix-contacts");
    }
}
