<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\File;
use App\Models\City;
use App\Models\ServiceType;
use App\Models\ProviderBranch;

echo "=== Testing Limerick Clinic Visits ===\n\n";

// Find Limerick city
$limerick = City::where('name', 'LIKE', '%Limerick%')->first();
if (!$limerick) {
    echo "❌ Limerick city not found\n";
    exit;
}
echo "✅ Found Limerick: ID {$limerick->id}, Name: {$limerick->name}\n";

// Find clinic visit service type (assuming it's service type 1 or similar)
$clinicVisit = ServiceType::where('name', 'LIKE', '%clinic%')
    ->orWhere('name', 'LIKE', '%visit%')
    ->orWhere('name', 'LIKE', '%consultation%')
    ->first();

if (!$clinicVisit) {
    echo "❌ Clinic visit service type not found\n";
    echo "Available service types:\n";
    ServiceType::all()->each(function($st) {
        echo "  - ID: {$st->id}, Name: {$st->name}\n";
    });
    exit;
}
echo "✅ Found Clinic Visit Service: ID {$clinicVisit->id}, Name: {$clinicVisit->name}\n\n";

// Create a test file for Limerick
$testFile = new File();
$testFile->city_id = $limerick->id;
$testFile->country_id = $limerick->country_id;
$testFile->service_type_id = $clinicVisit->id;
$testFile->address = "Test Address, Limerick";

echo "=== Test File Details ===\n";
echo "City ID: {$testFile->city_id}\n";
echo "Country ID: {$testFile->country_id}\n";
echo "Service Type ID: {$testFile->service_type_id}\n\n";

// Test the availableBranches method
echo "=== Testing availableBranches() Method ===\n";
$branches = $testFile->availableBranches();

echo "City Branches Count: " . $branches['cityBranches']->count() . "\n";
echo "All Branches Count: " . $branches['allBranches']->count() . "\n\n";

if ($branches['cityBranches']->count() > 0) {
    echo "=== City Branches (Limerick) ===\n";
    foreach ($branches['cityBranches'] as $branch) {
        echo "Branch: {$branch->branch_name}\n";
        echo "  Provider: {$branch->provider->name}\n";
        echo "  City: {$branch->city->name}\n";
        echo "  Country: {$branch->provider->country->name}\n";
        echo "  Priority: {$branch->priority}\n";
        echo "  All Country: " . ($branch->all_country ? 'Yes' : 'No') . "\n";
        echo "  Direct City ID: {$branch->city_id}\n";
        echo "  Cities via Pivot: " . $branch->cities->pluck('name')->join(', ') . "\n";
        echo "  ---\n";
    }
} else {
    echo "❌ No city branches found for Limerick\n";
}

if ($branches['allBranches']->count() > 0) {
    echo "\n=== All Branches (Same Country) ===\n";
    foreach ($branches['allBranches'] as $branch) {
        echo "Branch: {$branch->branch_name}\n";
        echo "  Provider: {$branch->provider->name}\n";
        echo "  City: {$branch->city->name}\n";
        echo "  Country: {$branch->provider->country->name}\n";
        echo "  Priority: {$branch->priority}\n";
        echo "  ---\n";
    }
} else {
    echo "❌ No branches found for the same country\n";
}

// Let's also check what branches exist in the database
echo "\n=== All Active Branches in Database ===\n";
$allBranches = ProviderBranch::where('status', 'Active')
    ->with(['provider.country', 'city', 'cities'])
    ->get();

echo "Total active branches: " . $allBranches->count() . "\n";

// Group by country
$branchesByCountry = $allBranches->groupBy('provider.country.name');
foreach ($branchesByCountry as $country => $branches) {
    echo "\nCountry: {$country} ({$branches->count()} branches)\n";
    foreach ($branches as $branch) {
        echo "  - {$branch->branch_name} (Provider: {$branch->provider->name})\n";
        echo "    City: {$branch->city->name}\n";
        echo "    Pivot Cities: " . $branch->cities->pluck('name')->join(', ') . "\n";
    }
}

echo "\n=== Test Complete ===\n";
