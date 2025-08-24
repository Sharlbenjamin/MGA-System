<?php

require_once 'vendor/autoload.php';

use App\Services\OcrService;

// Test the OCR service
$ocrService = new OcrService();

// Create a test image path (you'll need to provide a real image)
$testImagePath = 'test_image.jpg'; // Replace with actual image path

if (file_exists($testImagePath)) {
    echo "Testing OCR with image: $testImagePath\n";
    
    try {
        $result = $ocrService->extractTextFromImage($testImagePath);
        
        echo "OCR Result:\n";
        print_r($result);
        
    } catch (Exception $e) {
        echo "OCR Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Test image not found: $testImagePath\n";
    echo "Please provide a test image to verify OCR functionality.\n";
}
