<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Filament\Resources\ContactResource;
use Illuminate\Console\Command;

class TestContactEditRoute extends Command
{
    protected $signature = 'test:contact-edit-route {id}';
    protected $description = 'Test contact edit route binding with a specific ID';

    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Testing contact edit route binding for ID: {$id}");
        
        // Test if contact exists
        $contact = Contact::findByRouteKey($id);
        if (!$contact) {
            $this->error("Contact not found!");
            return;
        }
        
        $this->info("Contact found: " . $contact->name);
        
        // Test the edit URL generation
        try {
            $editUrl = ContactResource::getUrl('edit', ['record' => $contact]);
            $this->info("Edit URL generated: " . $editUrl);
        } catch (\Exception $e) {
            $this->error("Error generating edit URL: " . $e->getMessage());
        }
        
        // Test the route key
        $this->info("Route key: " . $contact->getRouteKey());
        $this->info("Route key name: " . $contact->getRouteKeyName());
    }
}
