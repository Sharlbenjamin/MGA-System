<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;

class TestAppointmentRequest extends Command
{
    protected $signature = 'test:appointment-request {file_id?}';
    protected $description = 'Test the appointment request functionality';

    public function handle()
    {
        $fileId = $this->argument('file_id');
        
        if (!$fileId) {
            $file = File::with(['patient.client', 'serviceType', 'country', 'city'])->first();
            if (!$file) {
                $this->error('No files found in the database.');
                return 1;
            }
            $fileId = $file->id;
        }

        $file = File::with(['patient.client', 'serviceType', 'country', 'city'])->find($fileId);
        
        if (!$file) {
            $this->error("File with ID {$fileId} not found.");
            return 1;
        }

        $this->info("Testing appointment request for file: {$file->mga_reference}");

        // Test available branches
        $branches = $file->availableBranches();
        $this->info("Available branches: " . $branches['cityBranches']->count() . " city branches, " . $branches['allBranches']->count() . " total branches");

        if ($branches['cityBranches']->isEmpty()) {
            $this->warn('No branches available for this file.');
            return 1;
        }

        // Test branch contacts
        $branchesWithContacts = 0;
        foreach ($branches['cityBranches'] as $branch) {
            $contact = $branch->primaryContact('Appointment');
            if ($contact && $contact->email) {
                $branchesWithContacts++;
                $this->info("Branch {$branch->branch_name} has contact: {$contact->email}");
            } else {
                $this->warn("Branch {$branch->branch_name} has no appointment contact or email");
            }
        }

        $this->info("Branches with contacts: {$branchesWithContacts}");

        // Test email sending (without actually sending)
        $this->info('Testing email template...');
        try {
            $branch = $branches['cityBranches']->first();
            $contact = $branch->primaryContact('Appointment');
            
            if ($contact && $contact->email) {
                $this->info("Would send email to: {$contact->email}");
                $this->info("Branch: {$branch->branch_name}");
                $this->info("File: {$file->mga_reference}");
            }
        } catch (\Exception $e) {
            $this->error("Error testing email: " . $e->getMessage());
        }

        $this->info('Appointment request test completed successfully!');
        return 0;
    }
} 