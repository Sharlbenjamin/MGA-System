<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupContacts extends Command
{
    protected $signature = 'contacts:backup {--path= : Custom backup path}';
    protected $description = 'Backup all contacts data to JSON file';

    public function handle()
    {
        $this->info('Creating contacts backup...');

        // Get all contacts
        $contacts = Contact::all();
        
        // Convert to array with all data
        $contactsData = $contacts->map(function ($contact) {
            return $contact->toArray();
        })->toArray();

        // Create backup data
        $backupData = [
            'timestamp' => now()->toISOString(),
            'total_contacts' => count($contactsData),
            'contacts' => $contactsData
        ];

        // Generate filename
        $filename = 'contacts_backup_' . now()->format('Y_m_d_H_i_s') . '.json';
        
        // Determine backup path
        $backupPath = $this->option('path') ?: storage_path('app/backups');
        
        // Create directory if it doesn't exist
        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $fullPath = $backupPath . '/' . $filename;

        // Save backup
        File::put($fullPath, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->info("✓ Backup created successfully!");
        $this->info("Location: {$fullPath}");
        $this->info("Total contacts backed up: " . count($contactsData));
        
        return 0;
    }
}
