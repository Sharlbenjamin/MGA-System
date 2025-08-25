<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Client;
use App\Models\Provider;
use App\Models\ProviderBranch;
use App\Models\Patient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecreateContactsWithUuids extends Command
{
    protected $signature = 'contacts:recreate-with-uuids {--dry-run : Show what would be done without actually doing it}';
    protected $description = 'Recreate all contacts with proper UUIDs while preserving all details';

    public function handle()
    {
        $this->info('Starting contact recreation process...');
        
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get all existing contacts
        $contacts = Contact::all();
        $this->info("Found {$contacts->count()} contacts to process");

        $successCount = 0;
        $errorCount = 0;

        foreach ($contacts as $contact) {
            try {
                $this->info("Processing contact ID: {$contact->id}, Name: {$contact->name}");
                
                // Store all the contact data
                $contactData = $contact->toArray();
                
                // Generate a new UUID
                $newId = Str::uuid()->toString();
                
                // Create new contact data with new UUID
                $newContactData = [
                    'id' => $newId,
                    'type' => $contactData['type'],
                    'client_id' => $contactData['client_id'],
                    'provider_id' => $contactData['provider_id'],
                    'branch_id' => $contactData['branch_id'],
                    'patient_id' => $contactData['patient_id'],
                    'name' => $contactData['name'],
                    'title' => $contactData['title'],
                    'email' => $contactData['email'],
                    'second_email' => $contactData['second_email'],
                    'phone_number' => $contactData['phone_number'],
                    'second_phone' => $contactData['second_phone'],
                    'country_id' => $contactData['country_id'],
                    'city_id' => $contactData['city_id'],
                    'address' => $contactData['address'],
                    'preferred_contact' => $contactData['preferred_contact'],
                    'status' => $contactData['status'],
                    'created_at' => $contactData['created_at'],
                    'updated_at' => $contactData['updated_at'],
                ];

                if (!$this->option('dry-run')) {
                    // Delete the old contact
                    $contact->delete();
                    
                    // Create the new contact with UUID
                    Contact::create($newContactData);
                }

                $this->info("✓ Successfully processed contact: {$contactData['name']} (Old ID: {$contact->id} → New ID: {$newId})");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("✗ Error processing contact {$contact->id}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("\n=== SUMMARY ===");
        $this->info("Successfully processed: {$successCount} contacts");
        $this->info("Errors: {$errorCount} contacts");
        
        if ($this->option('dry-run')) {
            $this->warn("This was a dry run. Run without --dry-run to actually perform the changes.");
        } else {
            $this->info("All contacts have been recreated with proper UUIDs!");
        }
    }
}
