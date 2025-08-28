<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\File;
use App\Models\Provider;
use App\Models\ProviderBranch;

echo "=== TESTING FILE ID 144 ===\n\n";

// Load file with all relationships
$file = File::with(['patient', 'serviceType', 'city', 'country'])->find(144);

if (!$file) {
    echo "❌ File ID 144 not found!\n";
    exit;
}

echo "📄 FILE DETAILS:\n";
echo "ID: {$file->id}\n";
echo "Patient: " . ($file->patient->name ?? 'N/A') . "\n";
echo "Service Type: " . ($file->serviceType->name ?? 'N/A') . " (ID: {$file->service_type_id})\n";
echo "City: " . ($file->city->name ?? 'N/A') . " (ID: {$file->city_id})\n";
echo "Country: " . ($file->country->name ?? 'N/A') . " (ID: {$file->country_id})\n";
echo "Address: {$file->address}\n\n";

echo "🔍 ANALYZING PROVIDERS AND BRANCHES:\n";

// Get all providers with branches
$providers = Provider::with(['branches.cities', 'branches.branchServices.serviceType', 'country'])
    ->whereHas('branches')
    ->get();
    
echo "Total providers with branches: " . $providers->count() . "\n";

// Show providers in the file's country
$countryProviders = $providers->where('country_id', $file->country_id);
echo "Providers in {$file->country->name}: " . $countryProviders->count() . "\n";

foreach ($countryProviders as $provider) {
    echo "\n  Provider: {$provider->name} (Status: {$provider->status})\n";
    echo "  Branches: " . $provider->branches->count() . "\n";
    
    foreach ($provider->branches as $branch) {
        $cities = $branch->cities ? $branch->cities->pluck('name')->join(', ') : 'None';
        $services = $branch->branchServices ? $branch->branchServices->pluck('serviceType.name')->join(', ') : 'None';
        
        echo "    - Branch: {$branch->name}\n";
        echo "      Cities: {$cities}\n";
        echo "      Services: {$services}\n";
        echo "      Priority: {$branch->priority}\n";
        echo "      Email: " . ($branch->email ?: 'None') . "\n";
    }
}

echo "\n🎯 EXPECTED FILTER RESULTS:\n";
echo "For filters: Country={$file->country->name}, Service Type={$file->serviceType->name}, City={$file->city->name}\n";

$matchingBranches = collect();

foreach ($countryProviders as $provider) {
    foreach ($provider->branches as $branch) {
        // Check if branch has the service type
        $hasService = $branch->branchServices && $branch->branchServices->contains('service_type_id', $file->service_type_id);
        
        // Check if branch has the city
        $hasCity = $branch->cities && $branch->cities->contains('id', $file->city_id);
        
        if ($hasService && $hasCity) {
            $matchingBranches->push([
                'provider' => $provider->name,
                'branch' => $branch->name,
                'priority' => $branch->priority,
                'email' => $branch->email ?: 'None'
            ]);
        }
    }
}

if ($matchingBranches->count() > 0) {
    echo "✅ Expected results ({$matchingBranches->count()} branches):\n";
    foreach ($matchingBranches as $branch) {
        echo "  - {$branch['provider']} > {$branch['branch']} (Priority: {$branch['priority']}, Email: {$branch['email']})\n";
    }
} else {
    echo "❌ No branches match the exact filters!\n";
}

echo "\n🔧 DEBUGGING INDIVIDUAL FILTERS:\n";

// Test each filter individually
echo "\n1. Country filter only ({$file->country->name}):\n";
$countryBranches = collect();
foreach ($countryProviders as $provider) {
    foreach ($provider->branches as $branch) {
        $countryBranches->push("{$provider->name} > {$branch->name}");
    }
}
echo "   Results: " . $countryBranches->count() . " branches\n";

// Test service type filter
echo "\n2. Service type filter only ({$file->serviceType->name}):\n";
$serviceBranches = collect();
foreach ($providers as $provider) {
    foreach ($provider->branches as $branch) {
        if ($branch->branchServices && $branch->branchServices->contains('service_type_id', $file->service_type_id)) {
            $serviceBranches->push("{$provider->name} > {$branch->name}");
        }
    }
}
echo "   Results: " . $serviceBranches->count() . " branches\n";

// Test city filter
echo "\n3. City filter only ({$file->city->name}):\n";
$cityBranches = collect();
foreach ($providers as $provider) {
    foreach ($provider->branches as $branch) {
        if ($branch->cities && $branch->cities->contains('id', $file->city_id)) {
            $cityBranches->push("{$provider->name} > {$branch->name}");
        }
    }
}
echo "   Results: " . $cityBranches->count() . " branches\n";

echo "\n✅ Test completed!\n";
