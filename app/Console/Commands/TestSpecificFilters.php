<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\Country;
use App\Models\City;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Builder;

class TestSpecificFilters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:specific-filters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the specific filter combination for France + Clinic Visit + Nice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Specific Filter Combination: France + Clinic Visit + Nice');
        $this->newLine();

        // Get the specific entities
        $france = Country::where('name', 'LIKE', '%France%')->first();
        $clinicVisit = ServiceType::where('name', 'LIKE', '%Clinic Visit%')->first();
        $nice = City::where('name', 'LIKE', '%Nice%')->first();

        if (!$france || !$clinicVisit || !$nice) {
            $this->error('❌ One or more entities not found!');
            if (!$france) $this->error('   France not found');
            if (!$clinicVisit) $this->error('   Clinic Visit not found');
            if (!$nice) $this->error('   Nice not found');
            return 1;
        }

        $this->line("✅ Found entities:");
        $this->line("   🏳️  Country: {$france->name} (ID: {$france->id})");
        $this->line("   🏥 Service: {$clinicVisit->name} (ID: {$clinicVisit->id})");
        $this->line("   🏙️  City: {$nice->name} (ID: {$nice->id})");
        $this->newLine();

        // Test each filter individually
        $this->testIndividualFilters($france, $clinicVisit, $nice);

        // Test combinations
        $this->testFilterCombinations($france, $clinicVisit, $nice);

        // Test the exact query that should work
        $this->testExactQuery($france, $clinicVisit, $nice);

        return 0;
    }

    /**
     * Test individual filters
     */
    private function testIndividualFilters($france, $clinicVisit, $nice)
    {
        $this->info('🔍 Testing Individual Filters:');

        // Country filter
        $countryBranches = ProviderBranch::whereHas('provider', function($q) use ($france) {
            $q->where('country_id', $france->id);
        })->get();
        $this->line("   🏳️  Branches in France: {$countryBranches->count()}");
        foreach ($countryBranches as $branch) {
            $this->line("     • {$branch->branch_name}");
        }

        // Service filter
        $serviceBranches = ProviderBranch::whereHas('branchServices', function($q) use ($clinicVisit) {
            $q->where('service_type_id', $clinicVisit->id)->where('is_active', 1);
        })->get();
        $this->line("   🏥 Branches with Clinic Visit: {$serviceBranches->count()}");

        // City filter
        $cityBranches = ProviderBranch::whereHas('cities', function($q) use ($nice) {
            $q->where('cities.id', $nice->id);
        })->get();
        $this->line("   🏙️  Branches in Nice: {$cityBranches->count()}");
        foreach ($cityBranches as $branch) {
            $this->line("     • {$branch->branch_name}");
        }

        $this->newLine();
    }

    /**
     * Test filter combinations
     */
    private function testFilterCombinations($france, $clinicVisit, $nice)
    {
        $this->info('🔗 Testing Filter Combinations:');

        // Country + Service
        $countryServiceBranches = ProviderBranch::whereHas('provider', function($q) use ($france) {
            $q->where('country_id', $france->id);
        })->whereHas('branchServices', function($q) use ($clinicVisit) {
            $q->where('service_type_id', $clinicVisit->id)->where('is_active', 1);
        })->get();
        $this->line("   🏳️ + 🏥 France + Clinic Visit: {$countryServiceBranches->count()}");
        foreach ($countryServiceBranches as $branch) {
            $this->line("     • {$branch->branch_name}");
        }

        // Country + City
        $countryCityBranches = ProviderBranch::whereHas('provider', function($q) use ($france) {
            $q->where('country_id', $france->id);
        })->whereHas('cities', function($q) use ($nice) {
            $q->where('cities.id', $nice->id);
        })->get();
        $this->line("   🏳️ + 🏙️  France + Nice: {$countryCityBranches->count()}");
        foreach ($countryCityBranches as $branch) {
            $this->line("     • {$branch->branch_name}");
        }

        // Service + City
        $serviceCityBranches = ProviderBranch::whereHas('branchServices', function($q) use ($clinicVisit) {
            $q->where('service_type_id', $clinicVisit->id)->where('is_active', 1);
        })->whereHas('cities', function($q) use ($nice) {
            $q->where('cities.id', $nice->id);
        })->get();
        $this->line("   🏥 + 🏙️  Clinic Visit + Nice: {$serviceCityBranches->count()}");
        foreach ($serviceCityBranches as $branch) {
            $this->line("     • {$branch->branch_name}");
        }

        $this->newLine();
    }

    /**
     * Test the exact query that should work
     */
    private function testExactQuery($france, $clinicVisit, $nice)
    {
        $this->info('🎯 Testing All Three Filters Combined:');

        $combinedBranches = ProviderBranch::whereHas('provider', function($q) use ($france) {
            $q->where('country_id', $france->id);
        })->whereHas('branchServices', function($q) use ($clinicVisit) {
            $q->where('service_type_id', $clinicVisit->id)->where('is_active', 1);
        })->whereHas('cities', function($q) use ($nice) {
            $q->where('cities.id', $nice->id);
        })->get();

        $this->line("   🏳️ + 🏥 + 🏙️  France + Clinic Visit + Nice: {$combinedBranches->count()}");
        
        if ($combinedBranches->count() > 0) {
            foreach ($combinedBranches as $branch) {
                $this->line("     ✅ {$branch->branch_name} (ID: {$branch->id})");
                $this->line("        Provider: {$branch->provider->name}");
                $this->line("        Country: {$branch->provider->country->name}");
                
                $cities = $branch->cities ? $branch->cities->pluck('name')->implode(', ') : 'None';
                $this->line("        Cities: {$cities}");
                
                $services = $branch->branchServices ? $branch->branchServices->pluck('serviceType.name')->implode(', ') : 'None';
                $this->line("        Services: {$services}");
            }
        } else {
            $this->error("   ❌ No branches match all three filters!");
            $this->line("   💡 This explains why you're getting no results in the UI.");
        }

        $this->newLine();
    }
}
