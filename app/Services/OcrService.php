<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class OcrService
{
    
    /**
     * Extract text data from a string input
     */
    public function extractTextFromString(string $text): array
    {
        try {
            Log::info('Processing text input', ['text_length' => strlen($text)]);
            
            // Parse the text to find structured data
            $extractedData = $this->parseExtractedText($text);
            
            return $extractedData;
        } catch (\Exception $e) {
            Log::error('Text processing failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
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
     * Parse extracted text to find structured data
     * This method analyzes the text and extracts specific fields
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
            'phone' => '',
            'country' => '',
            'city' => '',
            'confidence' => 0
        ];
        
        // Split text into lines and process each line
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Look for patient name patterns (multiple formats)
            if (preg_match('/Patient\s*:\s*(.+)/i', $line, $matches)) {
                $data['patient_name'] = trim($matches[1]);
            }
            
            // Look for patient name in pipe-separated format
            if (preg_match('/(\d{7}-\d{2})\s*\|\s*(.+?)\s*\|\s*(\d+)/i', $line, $matches)) {
                $data['client_reference'] = trim($matches[1]);
                $data['patient_name'] = trim($matches[2]);
                $data['extra_field'] = 'Policy: ' . trim($matches[3]);
            }
            
            // Look for address patterns (bullet point format)
            if (preg_match('/•\s*Address\s*:\s*(.+)/i', $line, $matches)) {
                $data['patient_address'] = trim($matches[1]);
            }
            
            // Look for city patterns (bullet point format)
            if (preg_match('/•\s*City\s*:\s*(.+)/i', $line, $matches)) {
                $data['city'] = trim($matches[1]);
            }
            
            // Look for country patterns (bullet point format)
            if (preg_match('/•\s*Country\s*:\s*(.+)/i', $line, $matches)) {
                $data['country'] = trim($matches[1]);
            }
            
            // Look for city patterns (non-bullet format)
            if (preg_match('/^City\s*:\s*(.+)/i', $line, $matches)) {
                $data['city'] = trim($matches[1]);
            }
            
            // Look for country patterns (non-bullet format)
            if (preg_match('/^Country\s*:\s*(.+)/i', $line, $matches)) {
                $data['country'] = trim($matches[1]);
            }
            
            // Look for telephone patterns
            if (preg_match('/•\s*Telephone\s*:\s*(.+)/i', $line, $matches)) {
                $data['phone'] = trim($matches[1]);
                $data['extra_field'] = ($data['extra_field'] ? $data['extra_field'] . ' | ' : '') . 'Phone: ' . trim($matches[1]);
            }
            
            // Look for phone patterns (non-bullet)
            if (preg_match('/Phone\s*:\s*(.+)/i', $line, $matches)) {
                $data['phone'] = trim($matches[1]);
                $data['extra_field'] = ($data['extra_field'] ? $data['extra_field'] . ' | ' : '') . 'Phone: ' . trim($matches[1]);
            }
            
            // Look for patient phone patterns
            if (preg_match('/Patient\s+Phone\s*:\s*(.+)/i', $line, $matches)) {
                $data['phone'] = trim($matches[1]);
                $data['extra_field'] = ($data['extra_field'] ? $data['extra_field'] . ' | ' : '') . 'Patient Phone: ' . trim($matches[1]);
            }
            
            // Look for DOB patterns (bullet point format)
            if (preg_match('/•\s*DOB\s*:\s*(\d{4}-\d{2}-\d{2})/i', $line, $matches)) {
                $data['date_of_birth'] = $matches[1];
            }
            
            // Look for D.O.B patterns (with dots)
            if (preg_match('/D\.O\.B\s*:\s*(\d{4}-\d{2}-\d{2})/i', $line, $matches)) {
                $data['date_of_birth'] = $matches[1];
            }
            
            // Look for symptoms patterns (bullet point format)
            if (preg_match('/•\s*Symptoms\s*:\s*(.+)/i', $line, $matches)) {
                $data['symptoms'] = trim($matches[1]);
            }
            
            // Look for symptoms patterns (non-bullet)
            if (preg_match('/Symptoms\s*:\s*(.+)/i', $line, $matches)) {
                $data['symptoms'] = trim($matches[1]);
            }
            
            // Look for reference number patterns (bullet point format)
            if (preg_match('/•\s*Our\s+Reference\s+number\s*:\s*(.+)/i', $line, $matches)) {
                $data['client_reference'] = trim($matches[1]);
            }
            
            // Look for Our Reference patterns (non-bullet)
            if (preg_match('/Our\s+Reference\s*:\s*(.+)/i', $line, $matches)) {
                $data['client_reference'] = trim($matches[1]);
            }
            
            // Look for nationality patterns
            if (preg_match('/•\s*Nationality\s*:\s*(.+)/i', $line, $matches)) {
                $data['extra_field'] = ($data['extra_field'] ? $data['extra_field'] . ' | ' : '') . 'Nationality: ' . trim($matches[1]);
            }
            
            // Look for assistance type patterns
            if (preg_match('/•\s*Kind\s+of\s+assistance\s*:\s*(.+)/i', $line, $matches)) {
                $data['service_type'] = trim($matches[1]);
            }
            
            // Look for Policy Number patterns
            if (preg_match('/Policy\s+Number\s*:\s*(.+)/i', $line, $matches)) {
                $data['extra_field'] = ($data['extra_field'] ? $data['extra_field'] . ' | ' : '') . 'Policy: ' . trim($matches[1]);
            }
            
            // Look for Medical Provider patterns
            if (preg_match('/Medical\s+Provider\s*:\s*(.+)/i', $line, $matches)) {
                $data['service_type'] = trim($matches[1]);
            }
            
            // Note: Country and city are extracted from specific fields above, not from address parsing
            
            // Also handle non-bullet point formats
            if (preg_match('/(?:address|location)\s*:\s*(.+)/i', $line, $matches)) {
                $data['patient_address'] = trim($matches[1]);
            }
            
            // Look for address in Patient Address section
            if (preg_match('/^Address\s*:\s*(.+)/i', $line, $matches)) {
                $data['patient_address'] = trim($matches[1]);
            }
            
            
            if (preg_match('/(?:dob|date of birth|birth date)\s*:\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $line, $matches)) {
                $data['date_of_birth'] = $matches[1];
            }
            
            if (preg_match('/(?:reference|ref|client ref)\s*:\s*([A-Z0-9\-]+)/i', $line, $matches)) {
                $data['client_reference'] = trim($matches[1]);
            }
            
            if (preg_match('/(?:symptoms|complaints)\s*:\s*(.+)/i', $line, $matches)) {
                $data['symptoms'] = trim($matches[1]);
            }
            
            if (preg_match('/(?:service|consultation|treatment|assistance)\s*:\s*(.+)/i', $line, $matches)) {
                $data['service_type'] = trim($matches[1]);
            }
        }
        
        // Set confidence based on how many fields were found
        $foundFields = 0;
        foreach ($data as $key => $value) {
            if ($key !== 'confidence' && !empty($value)) {
                $foundFields++;
            }
        }
        $data['confidence'] = min(100, ($foundFields / 7) * 100);
        
        // Map service types to database IDs
        $data['service_type'] = $this->mapServiceType($data['service_type']);
        
        return $data;
    }
    
    /**
     * Parse address to extract city and country
     */
    private function parseAddress(string $address): array
    {
        $result = ['city' => '', 'country' => ''];
        
        // Common patterns for address parsing
        $patterns = [
            // Pattern: "3, University Halls, Newcastle Rd, Galway, Ireland"
            '/.*?,\s*([^,]+),\s*([^,]+)$/i' => ['city', 'country'],
            
            // Pattern: "Street, City, Country" (more specific)
            '/^[^,]+,\s*([^,]+),\s*([^,]+)$/i' => ['city', 'country'],
            
            // Pattern: "Address, City, Country" (with more context)
            '/.*?,\s*([A-Za-z\s]+),\s*([A-Za-z\s]+)$/i' => ['city', 'country'],
            
            // Pattern: "City, Country" (simple format)
            '/^([^,]+),\s*([^,]+)$/i' => ['city', 'country'],
        ];
        
        foreach ($patterns as $pattern => $fields) {
            if (preg_match($pattern, $address, $matches)) {
                if (isset($matches[1]) && isset($matches[2])) {
                    $city = trim($matches[1]);
                    $country = trim($matches[2]);
                    
                    // Basic validation - skip if city or country is too short or contains numbers
                    if (strlen($city) >= 2 && strlen($country) >= 2 && 
                        !preg_match('/^\d+$/', $city) && !preg_match('/^\d+$/', $country)) {
                        $result[$fields[0]] = $city;
                        $result[$fields[1]] = $country;
                        break;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Map service type names to database IDs
     */
    private function mapServiceType(string $serviceType): string
    {
        $serviceType = strtolower(trim($serviceType));
        
        $mappings = [
            'medical center' => '5', // Clinic Visit
            'medical centre' => '5', // Clinic Visit
            'clinic' => '5', // Clinic Visit
            'clinic visit' => '5', // Clinic Visit
            'house call' => '1',
            'telemedicine' => '2',
            'hospital visit' => '3',
            'dental clinic' => '4',
        ];
        
        return $mappings[$serviceType] ?? $serviceType;
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
            'phone' => trim($data['phone'] ?? ''),
            'country' => trim($data['country'] ?? ''),
            'city' => trim($data['city'] ?? ''),
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
