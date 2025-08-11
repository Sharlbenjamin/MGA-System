<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DistanceCalculationService;
use App\Models\File;
use App\Models\ProviderBranch;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DistanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_distance_calculation_service_can_be_instantiated()
    {
        $service = new DistanceCalculationService();
        $this->assertInstanceOf(DistanceCalculationService::class, $service);
    }

    public function test_distance_calculation_returns_null_for_empty_addresses()
    {
        $service = new DistanceCalculationService();
        $result = $service->calculateDistance('', '');
        $this->assertNull($result);
    }

    public function test_distance_calculation_returns_null_for_missing_api_key()
    {
        config(['services.google.maps_api_key' => null]);
        
        $service = new DistanceCalculationService();
        $result = $service->calculateDistance('123 Main St', '456 Oak Ave');
        $this->assertNull($result);
    }

    public function test_file_distance_calculation_returns_null_for_missing_file_address()
    {
        $file = File::factory()->create(['address' => null]);
        
        $service = new DistanceCalculationService();
        $result = $service->calculateFileToBranchDistance($file);
        $this->assertNull($result);
    }

    public function test_file_distance_calculation_returns_null_for_missing_provider_branch()
    {
        $file = File::factory()->create([
            'address' => '123 Main St',
            'provider_branch_id' => null
        ]);
        
        $service = new DistanceCalculationService();
        $result = $service->calculateFileToBranchDistance($file);
        $this->assertNull($result);
    }

    public function test_formatted_distance_returns_na_for_null_data()
    {
        $service = new DistanceCalculationService();
        $result = $service->getFormattedDistance(null);
        $this->assertEquals('N/A', $result);
    }

    public function test_file_model_has_distance_methods()
    {
        $file = File::factory()->create(['address' => '123 Main St']);
        
        $this->assertTrue(method_exists($file, 'getDistanceToBranch'));
        $this->assertTrue(method_exists($file, 'getFormattedDistanceToBranch'));
    }
} 