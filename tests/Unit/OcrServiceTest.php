<?php

namespace Tests\Unit;

use App\Services\OcrService;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    protected OcrService $ocrService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ocrService = new OcrService();
    }

    public function test_extract_text_from_image_returns_expected_structure()
    {
        // Create a temporary test image path
        $testImagePath = storage_path('app/test-image.jpg');
        
        // Test the extraction method
        $result = $this->ocrService->extractTextFromImage($testImagePath);
        
        // Assert the result has the expected structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('patient_name', $result);
        $this->assertArrayHasKey('date_of_birth', $result);
        $this->assertArrayHasKey('client_reference', $result);
        $this->assertArrayHasKey('service_type', $result);
        $this->assertArrayHasKey('patient_address', $result);
        $this->assertArrayHasKey('symptoms', $result);
        $this->assertArrayHasKey('extra_field', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_determine_gender_from_name()
    {
        // Test male names
        $this->assertEquals('Male', $this->ocrService->determineGenderFromName('John Doe'));
        $this->assertEquals('Male', $this->ocrService->determineGenderFromName('Michael Smith'));
        
        // Test female names
        $this->assertEquals('Female', $this->ocrService->determineGenderFromName('Mary Johnson'));
        $this->assertEquals('Female', $this->ocrService->determineGenderFromName('Jennifer Wilson'));
        
        // Test unknown names (should default to Female)
        $this->assertEquals('Female', $this->ocrService->determineGenderFromName('Unknown Name'));
        $this->assertEquals('Female', $this->ocrService->determineGenderFromName(''));
    }

    public function test_extract_text_from_image_returns_sample_data()
    {
        // Test with non-existent image path (should still return sample data in current implementation)
        $result = $this->ocrService->extractTextFromImage('non-existent-path.jpg');
        
        // Should return sample data structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('patient_name', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertEquals('John Doe', $result['patient_name']);
        $this->assertEquals(85, $result['confidence']);
    }
}
