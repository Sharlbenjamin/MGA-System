<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;

class VerifyBranchDataMigration extends Command
{
    protected $signature = 'branches:verify-migration';
    protected $description = 'Verify that contact data and costs were migrated correctly';

    public function handle()
    {
        $this->info('Verifying branch data migration...');

        $branches = ProviderBranch::with(['gopContact', 'operationContact', 'financialContact', 'branchServices'])
            ->get();

        $this->table(
            [
                'Branch Name', 
                'Direct Email', 
                'Operation Contact Email', 
                'GOP Contact Email', 
                'Financial Contact Email',
                'Direct Phone', 
                'Address',
                'Branch Services Count'
            ],
            $branches->map(function ($branch) {
                return [
                    $branch->branch_name,
                    $branch->email ?? 'NULL',
                    $branch->operationContact?->email ?? 'NULL',
                    $branch->gopContact?->email ?? 'NULL',
                    $branch->financialContact?->email ?? 'NULL',
                    $branch->phone ?? 'NULL',
                    $branch->address ? (strlen($branch->address) > 50 ? substr($branch->address, 0, 50) . '...' : $branch->address) : 'NULL',
                    $branch->branchServices->count(),
                ];
            })->toArray()
        );

        $this->info("\nSummary:");
        $this->info("Total branches: " . $branches->count());
        $this->info("Branches with direct email: " . $branches->whereNotNull('email')->count());
        $this->info("Branches with direct phone: " . $branches->whereNotNull('phone')->count());
        $this->info("Branches with address: " . $branches->whereNotNull('address')->count());
        $this->info("Branches with branch services: " . $branches->where('branchServices.count', '>', 0)->count());

        // Check for potential issues
        $this->info("\nPotential Issues:");
        $branchesWithoutEmail = $branches->whereNull('email')
            ->whereNull('operationContact.email')
            ->whereNull('gopContact.email')
            ->whereNull('financialContact.email');
        if ($branchesWithoutEmail->count() > 0) {
            $this->warn("Branches without any email: " . $branchesWithoutEmail->count());
            $this->table(
                ['Branch Name', 'Provider'],
                $branchesWithoutEmail->map(function ($branch) {
                    return [$branch->branch_name, $branch->provider->name ?? 'N/A'];
                })->toArray()
            );
        }

        $branchesWithoutServices = $branches->where('branchServices.count', 0);
        if ($branchesWithoutServices->count() > 0) {
            $this->warn("Branches without branch services: " . $branchesWithoutServices->count());
        }

        $this->info("\nMigration verification completed!");
    }
}
