<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;

class CheckBranchContacts extends Command
{
    protected $signature = 'branches:check-contacts';
    protected $description = 'Check what contact data exists for provider branches';

    public function handle()
    {
        $this->info('Checking branch contact data...');

        $branches = ProviderBranch::with(['gopContact', 'operationContact', 'financialContact'])
            ->get();

        $this->info("\nContact Relationship Summary:");
        $this->info("Total branches: " . $branches->count());
        $this->info("Branches with GOP contact: " . $branches->whereNotNull('gop_contact_id')->count());
        $this->info("Branches with Operation contact: " . $branches->whereNotNull('operation_contact_id')->count());
        $this->info("Branches with Financial contact: " . $branches->whereNotNull('financial_contact_id')->count());

        $this->info("\nContact Data Summary:");
        $branchesWithGopEmail = $branches->filter(function ($branch) {
            return $branch->gopContact && $branch->gopContact->email;
        })->count();
        $this->info("Branches with GOP contact email: " . $branchesWithGopEmail);

        $branchesWithOperationEmail = $branches->filter(function ($branch) {
            return $branch->operationContact && $branch->operationContact->email;
        })->count();
        $this->info("Branches with Operation contact email: " . $branchesWithOperationEmail);

        $branchesWithFinancialEmail = $branches->filter(function ($branch) {
            return $branch->financialContact && $branch->financialContact->email;
        })->count();
        $this->info("Branches with Financial contact email: " . $branchesWithFinancialEmail);

        // Show sample branches with contact data
        $this->info("\nSample branches with contact data:");
        $branchesWithContacts = $branches->filter(function ($branch) {
            return ($branch->gopContact && $branch->gopContact->email) ||
                   ($branch->operationContact && $branch->operationContact->email) ||
                   ($branch->financialContact && $branch->financialContact->email);
        })->take(10);

        if ($branchesWithContacts->count() > 0) {
            $this->table(
                ['Branch Name', 'GOP Email', 'Operation Email', 'Financial Email'],
                $branchesWithContacts->map(function ($branch) {
                    return [
                        $branch->branch_name,
                        $branch->gopContact?->email ?? 'NULL',
                        $branch->operationContact?->email ?? 'NULL',
                        $branch->financialContact?->email ?? 'NULL',
                    ];
                })->toArray()
            );
        } else {
            $this->warn("No branches found with contact email data!");
        }

        // Check if contact fields exist in provider_branches table
        $this->info("\nChecking if contact fields exist in provider_branches table:");
        $sampleBranch = $branches->first();
        if ($sampleBranch) {
            $this->info("Email field exists: " . (isset($sampleBranch->email) ? 'YES' : 'NO'));
            $this->info("Phone field exists: " . (isset($sampleBranch->phone) ? 'YES' : 'NO'));
            $this->info("Address field exists: " . (isset($sampleBranch->address) ? 'YES' : 'NO'));
        }
    }
}
