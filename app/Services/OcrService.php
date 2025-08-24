<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Image\Image;

class OcrService
{
    /**
     * Extract text data from an uploaded image
     */
    public function extractTextFromImage($imagePath): array
    {
        try {
            // For now, we'll use a simple approach that simulates OCR
            // In a real implementation, you would integrate with a proper OCR service
            // like Google Vision API, AWS Textract, or Tesseract
            
            $extractedData = $this->simulateOcrExtraction($imagePath);
            
            return $extractedData;
        } catch (\Exception $e) {
            Log::error('OCR extraction failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath
            ]);
            
            return [
                'patient_name' => '',
                'date_of_birth' => '',
                'client_reference' => '',
                'service_type' => '',
                'patient_address' => '',
                'symptoms' => '',
                'extra_field' => '',
                'confidence' => 0
            ];
        }
    }
    
    /**
     * Simulate OCR extraction - in production, replace with actual OCR service
     */
    private function simulateOcrExtraction($imagePath): array
    {
        // Validate that the image file exists
        if (!file_exists($imagePath)) {
            Log::error('Image file not found', ['path' => $imagePath]);
            throw new \Exception('Image file not found');
        }
        
        // Get image information
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            Log::error('Invalid image file', ['path' => $imagePath]);
            throw new \Exception('Invalid image file');
        }
        
        Log::info('Processing image', [
            'path' => $imagePath,
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2]
        ]);
        
        // For now, return sample data that would be extracted
        // In a real implementation, you would:
        // 1. Use Google Vision API: https://cloud.google.com/vision
        // 2. Use AWS Textract: https://aws.amazon.com/textract/
        // 3. Use Tesseract OCR: https://github.com/tesseract-ocr/tesseract
        
        // Example of how to integrate with Google Vision API:
        /*
        $client = new \Google\Cloud\Vision\V1\ImageAnnotatorClient();
        $image = file_get_contents($imagePath);
        $response = $client->documentTextDetection($image);
        $text = $response->getFullTextAnnotation()->getText();
        
        // Parse the text to extract structured data
        return $this->parseExtractedText($text);
        */
        
        // Real OCR processing using Tesseract
        try {
            // Check if Tesseract is available
            $tesseractPath = \exec('which tesseract');
            if (empty($tesseractPath)) {
                throw new \Exception('Tesseract not found. Please install Tesseract OCR.');
            }
            
            // Create a temporary output file for Tesseract
            $tempOutputFile = \tempnam(\sys_get_temp_dir(), 'ocr_output_');
            
            // Run Tesseract OCR on the image
            $command = "tesseract \"{$imagePath}\" \"{$tempOutputFile}\" --psm 6 -l eng";
            $output = [];
            $returnCode = 0;
            
            \exec($command . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new \Exception('Tesseract OCR failed: ' . implode("\n", $output));
            }
            
            // Read the extracted text
            $extractedText = \file_get_contents($tempOutputFile . '.txt');
            
            // Clean up temporary files
            @\unlink($tempOutputFile);
            @\unlink($tempOutputFile . '.txt');
            
            if (empty($extractedText)) {
                throw new \Exception('No text was extracted from the image');
            }
            
            Log::info('OCR text extracted', [
                'image_path' => $imagePath,
                'extracted_text' => $extractedText
            ]);
            
            // Parse the extracted text to find structured data
            return $this->parseExtractedText($extractedText);
            
        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'error' => $e->getMessage(),
                'image_path' => $imagePath
            ]);
            
            // Fallback to sample data if OCR fails
            return [
                'patient_name' => 'OCR Failed - ' . $e->getMessage(),
                'date_of_birth' => '',
                'client_reference' => '',
                'service_type' => '',
                'patient_address' => '',
                'symptoms' => '',
                'extra_field' => 'OCR Error: ' . $e->getMessage(),
                'confidence' => 0
            ];
        }
    }
    
    /**
     * Parse extracted text to find structured data
     * This method would analyze the OCR text and extract specific fields
     */
    private function parseExtractedText($text): array
    {
        $data = [
            'patient_name' => '',
            'date_of_birth' => '',
            'client_reference' => '',
            'service_type' => '',
            'patient_address' => '',
            'symptoms' => '',
            'extra_field' => '',
            'confidence' => 0
        ];
        
        // Example parsing logic (you would customize this based on your document format)
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for patient name patterns
            if (preg_match('/(?:patient|name|full name)[\s:]+(.+)/i', $line, $matches)) {
                $data['patient_name'] = trim($matches[1]);
            }
            
            // Look for date of birth patterns
            if (preg_match('/(?:dob|date of birth|birth date)[\s:]+(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $line, $matches)) {
                $data['date_of_birth'] = $matches[1];
            }
            
            // Look for client reference patterns
            if (preg_match('/(?:reference|ref|client ref)[\s:]+([A-Z0-9]+)/i', $line, $matches)) {
                $data['client_reference'] = trim($matches[1]);
            }
            
            // Look for service type patterns
            if (preg_match('/(?:service|consultation|treatment)[\s:]+(.+)/i', $line, $matches)) {
                $data['service_type'] = trim($matches[1]);
            }
            
            // Look for address patterns
            if (preg_match('/(?:address|location)[\s:]+(.+)/i', $line, $matches)) {
                $data['patient_address'] = trim($matches[1]);
            }
            
            // Look for symptoms patterns
            if (preg_match('/(?:symptoms|complaints)[\s:]+(.+)/i', $line, $matches)) {
                $data['symptoms'] = trim($matches[1]);
            }
        }
        
        return $data;
    }
    
    /**
     * Determine gender from name (simple implementation)
     */
    public function determineGenderFromName($name): string
    {
        if (empty($name)) {
            return 'Female'; // Default as requested
        }
        
        // Simple gender detection based on common names
        $maleNames = ['john', 'michael', 'david', 'james', 'robert', 'william', 'richard', 'joseph', 'thomas', 'christopher'];
        $femaleNames = ['mary', 'patricia', 'jennifer', 'linda', 'elizabeth', 'barbara', 'susan', 'jessica', 'sarah', 'karen'];
        
        $firstName = strtolower(explode(' ', trim($name))[0]);
        
        if (in_array($firstName, $maleNames)) {
            return 'Male';
        } elseif (in_array($firstName, $femaleNames)) {
            return 'Female';
        }
        
        // Default to Female as requested
        return 'Female';
    }
    
    /**
     * Clean and validate extracted data
     */
    public function cleanExtractedData(array $data): array
    {
        return [
            'patient_name' => trim($data['patient_name'] ?? ''),
            'date_of_birth' => $this->formatDate($data['date_of_birth'] ?? ''),
            'client_reference' => trim($data['client_reference'] ?? ''),
            'service_type' => trim($data['service_type'] ?? ''),
            'patient_address' => trim($data['patient_address'] ?? ''),
            'symptoms' => trim($data['symptoms'] ?? ''),
            'extra_field' => trim($data['extra_field'] ?? ''),
            'confidence' => (int) ($data['confidence'] ?? 0)
        ];
    }
    
    /**
     * Format date string to Y-m-d format
     */
    private function formatDate($dateString): string
    {
        if (empty($dateString)) {
            return '';
        }
        
        // Try to parse various date formats
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return '';
    }
}
