<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use Illuminate\Support\Facades\DB;

class CopyContactData extends Command
{
    protected $signature = 'branches:copy-contact-data';
    protected $description = 'Manually copy contact data from contacts to provider_branches';

    public function handle()
    {
        $this->info('Copying contact data to provider branches...');

        $branches = ProviderBranch::with(['gopContact', 'operationContact', 'financialContact'])
            ->get();

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($branches as $branch) {
            $hasUpdate = false;
            $updateData = [];

            // Priority: Operation Contact > GOP Contact > Financial Contact
            
            // Check Operation Contact first (highest priority)
            if ($branch->operationContact) {
                $contact = $branch->operationContact;
                
                if (!$updateData['email'] && $contact->email) {
                    $updateData['email'] = $contact->preferred_contact === 'Email' ? $contact->email : 
                                         ($contact->preferred_contact === 'Second Email' ? $contact->second_email : 
                                         ($contact->email ?: $contact->second_email));
                }
                
                if (!$updateData['phone'] && $contact->phone_number) {
                    $updateData['phone'] = $contact->preferred_contact === 'Phone' ? $contact->phone_number : 
                                         ($contact->preferred_contact === 'Second Phone' ? $contact->second_phone : 
                                         ($contact->phone_number ?: $contact->second_phone));
                }
                
                if (!$updateData['address'] && $contact->address) {
                    $updateData['address'] = $contact->address;
                }
            }

            // Check GOP Contact second
            if ($branch->gopContact) {
                $contact = $branch->gopContact;
                
                if (!$updateData['email'] && $contact->email) {
                    $updateData['email'] = $contact->preferred_contact === 'Email' ? $contact->email : 
                                         ($contact->preferred_contact === 'Second Email' ? $contact->second_email : 
                                         ($contact->email ?: $contact->second_email));
                }
                
                if (!$updateData['phone'] && $contact->phone_number) {
                    $updateData['phone'] = $contact->preferred_contact === 'Phone' ? $contact->phone_number : 
                                         ($contact->preferred_contact === 'Second Phone' ? $contact->second_phone : 
                                         ($contact->phone_number ?: $contact->second_phone));
                }
                
                if (!$updateData['address'] && $contact->address) {
                    $updateData['address'] = $contact->address;
                }
            }

            // Check Financial Contact third
            if ($branch->financialContact) {
                $contact = $branch->financialContact;
                
                if (!$updateData['email'] && $contact->email) {
                    $updateData['email'] = $contact->preferred_contact === 'Email' ? $contact->email : 
                                         ($contact->preferred_contact === 'Second Email' ? $contact->second_email : 
                                         ($contact->email ?: $contact->second_email));
                }
                
                if (!$updateData['phone'] && $contact->phone_number) {
                    $updateData['phone'] = $contact->preferred_contact === 'Phone' ? $contact->phone_number : 
                                         ($contact->preferred_contact === 'Second Phone' ? $contact->second_phone : 
                                         ($contact->phone_number ?: $contact->second_phone));
                }
                
                if (!$updateData['address'] && $contact->address) {
                    $updateData['address'] = $contact->address;
                }
            }

            // Update the branch if we have data
            if (!empty($updateData)) {
                $branch->update($updateData);
                $updatedCount++;
                $this->line("✓ Updated: {$branch->branch_name} - Email: " . ($updateData['email'] ?? 'NULL') . ", Phone: " . ($updateData['phone'] ?? 'NULL'));
            } else {
                $skippedCount++;
                $this->line("✗ Skipped: {$branch->branch_name} (no contact data)");
            }
        }

        $this->info("\nCopy completed!");
        $this->info("Updated: {$updatedCount} branches");
        $this->info("Skipped: {$skippedCount} branches");
    }
}
