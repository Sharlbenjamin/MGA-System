<?php

require_once 'vendor/autoload.php';

use App\Models\File;
use App\Models\Client;
use App\Models\Provider;
use App\Services\GoogleMeetService;

// This is a test script to verify the Google Meet functionality
// Run with: php test_google_meet.php

echo "Testing Google Meet functionality with phone fields...\n";

// Test 1: Check if we can access the new phone and email fields
echo "\n1. Testing new phone and email fields:\n";

// Test Client model
$client = new Client();
echo "Client fillable fields include phone and email: " . 
     (in_array('phone', $client->getFillable()) && in_array('email', $client->getFillable()) ? 'YES' : 'NO') . "\n";

// Test Provider model  
$provider = new Provider();
echo "Provider fillable fields include phone and email: " . 
     (in_array('phone', $provider->getFillable()) && in_array('email', $provider->getFillable()) ? 'YES' : 'NO') . "\n";

// Test 2: Check if File model has phone and email fields
echo "\n2. Testing File model phone and email fields:\n";
$file = new File();
echo "File fillable fields include phone and email: " . 
     (in_array('phone', $file->getFillable()) && in_array('email', $file->getFillable()) ? 'YES' : 'NO') . "\n";

// Test 3: Check if GoogleMeetService can be instantiated
echo "\n3. Testing GoogleMeetService:\n";
try {
    $googleMeetService = new GoogleMeetService();
    echo "GoogleMeetService can be instantiated: YES\n";
} catch (Exception $e) {
    echo "GoogleMeetService instantiation failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";
echo "\nTo test the actual Google Meet creation:\n";
echo "1. Create a file with service_type_id = 2 (Telemedicine)\n";
echo "2. Add phone numbers to the file and provider\n";
echo "3. Create an appointment and set status to 'Confirmed'\n";
echo "4. The system will automatically generate a Google Meet link\n";
echo "5. Check the Google Calendar event description for phone numbers\n"; 