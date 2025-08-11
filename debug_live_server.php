<?php

// Debug script for live server - save this as debug_live_server.php and run it
// Usage: php debug_live_server.php

echo "=== MGA System Distance Calculation Debug ===\n\n";

// Load Laravel
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\Contact;
use App\Services\DistanceCalculationService;

// Test specific file and branch
$fileId = 121;
$branchId = 58;

echo "Testing File ID: {$fileId} and Branch ID: {$branchId}\n\n";

// 1. Check if file exists
echo "1. Checking File...\n";
$file = File::with(['providerBranch.operationContact'])->find($fileId);
if (!$file) {
    echo "❌ File with ID {$fileId} not found!\n";
    exit(1);
}
echo "✅ File found: {$file->mga_reference}\n";

// 2. Check file address
echo "\n2. Checking File Address...\n";
if ($file->address) {
    echo "✅ File address: {$file->address}\n";
} else {
    echo "❌ File address is empty or null\n";
}

// 3. Check provider branch
echo "\n3. Checking Provider Branch...\n";
if ($file->providerBranch) {
    echo "✅ Provider Branch: {$file->providerBranch->branch_name} (ID: {$file->providerBranch->id})\n";
} else {
    echo "❌ No provider branch assigned to this file\n";
}

// 4. Check operation contact
echo "\n4. Checking Operation Contact...\n";
if ($file->providerBranch && $file->providerBranch->operationContact) {
    $operationContact = $file->providerBranch->operationContact;
    echo "✅ Operation Contact: {$operationContact->name} (ID: {$operationContact->id})\n";
    
    if ($operationContact->address) {
        echo "✅ Operation Contact address: {$operationContact->address}\n";
    } else {
        echo "❌ Operation Contact address is empty or null\n";
    }
} else {
    echo "❌ No operation contact found for provider branch\n";
}

// 5. Check API key
echo "\n5. Checking API Key...\n";
$apiKey = config('services.google.maps_api_key');
if ($apiKey) {
    echo "✅ Google Maps API key is configured\n";
    echo "   Key preview: " . substr($apiKey, 0, 10) . "...\n";
} else {
    echo "❌ Google Maps API key is not configured\n";
    echo "   Add GOOGLE_MAPS_API_KEY to your .env file\n";
}

// 6. Test distance calculation
echo "\n6. Testing Distance Calculation...\n";
$service = new DistanceCalculationService();

if ($file->address && $file->providerBranch && $file->providerBranch->operationContact && $file->providerBranch->operationContact->address) {
    echo "Attempting distance calculation...\n";
    
    try {
        $distanceData = $service->calculateFileToBranchDistance($file);
        
        if ($distanceData) {
            echo "✅ Distance calculation successful!\n";
            echo "   Distance: {$distanceData['distance']}\n";
            echo "   Duration: {$distanceData['duration']}\n";
            echo "   Duration (minutes): {$distanceData['duration_minutes']}\n";
            echo "   Formatted: " . $service->getFormattedDistance($distanceData) . "\n";
        } else {
            echo "❌ Distance calculation returned null\n";
            echo "   This could be due to:\n";
            echo "   - API key issues\n";
            echo "   - Network problems\n";
            echo "   - Invalid addresses\n";
            echo "   - API quota exceeded\n";
        }
    } catch (\Exception $e) {
        echo "❌ Exception during distance calculation: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Cannot calculate distance - missing required data\n";
}

// 7. Test with specific branch
echo "\n7. Testing with Specific Branch ID: {$branchId}...\n";
$branch = ProviderBranch::with('operationContact')->find($branchId);

if ($branch) {
    echo "✅ Branch found: {$branch->branch_name}\n";
    
    if ($branch->operationContact) {
        echo "✅ Branch operation contact: {$branch->operationContact->name}\n";
        
        if ($branch->operationContact->address) {
            echo "✅ Branch operation contact address: {$branch->operationContact->address}\n";
            
            // Test direct distance calculation
            echo "Testing direct distance calculation...\n";
            $directResult = $service->calculateDistance($file->address, $branch->operationContact->address);
            
            if ($directResult) {
                echo "✅ Direct calculation successful!\n";
                echo "   Distance: {$directResult['distance']}\n";
                echo "   Duration: {$directResult['duration']}\n";
                echo "   Duration (minutes): {$directResult['duration_minutes']}\n";
            } else {
                echo "❌ Direct calculation failed\n";
            }
        } else {
            echo "❌ Branch operation contact address is empty\n";
        }
    } else {
        echo "❌ Branch has no operation contact\n";
    }
} else {
    echo "❌ Branch with ID {$branchId} not found\n";
}

// 8. Check database relationships
echo "\n8. Database Relationship Check...\n";
echo "File provider_branch_id: " . ($file->provider_branch_id ?: 'NULL') . "\n";
if ($file->providerBranch) {
    echo "Provider Branch operation_contact_id: " . ($file->providerBranch->operation_contact_id ?: 'NULL') . "\n";
}

echo "\n=== Debug Complete ===\n"; 