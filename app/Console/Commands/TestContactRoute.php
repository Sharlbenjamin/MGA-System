<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

class TestContactRoute extends Command
{
    protected $signature = 'test:contact-route {id}';
    protected $description = 'Test contact route binding with a specific ID';

    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Testing contact route binding for ID: {$id}");
        
        // Test different approaches
        $this->info("1. Testing static::find({$id}):");
        $contact1 = Contact::find($id);
        $this->line($contact1 ? "Found: " . $contact1->name : "Not found");
        
        $this->info("2. Testing where('id', '=', {$id}):");
        $contact2 = Contact::where('id', '=', $id)->first();
        $this->line($contact2 ? "Found: " . $contact2->name : "Not found");
        
        $this->info("3. Testing where('id', '=', '{$id}'):");
        $contact3 = Contact::where('id', '=', (string) $id)->first();
        $this->line($contact3 ? "Found: " . $contact3->name : "Not found");
        
        $this->info("4. Testing findByRouteKey({$id}):");
        $contact4 = Contact::findByRouteKey($id);
        $this->line($contact4 ? "Found: " . $contact4->name : "Not found");
        
        $this->info("5. Testing resolveRouteBinding({$id}):");
        $contact5 = (new Contact)->resolveRouteBinding($id);
        $this->line($contact5 ? "Found: " . $contact5->name : "Not found");
        
        // Show all contacts with their IDs
        $this->info("6. All contacts in database:");
        $allContacts = Contact::all(['id', 'name']);
        foreach ($allContacts as $contact) {
            $this->line("ID: {$contact->id} | Name: {$contact->name}");
        }
    }
}
