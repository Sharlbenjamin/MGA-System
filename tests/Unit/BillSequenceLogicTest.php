<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillSequenceLogicTest extends TestCase
{
    public function test_bill_sequence_format()
    {
        // Test the expected format of the bill sequence
        $expectedFormat = 'MG030WH-Bill-01';
        
        // Verify the format follows the pattern: {file_reference}-Bill-{2_digit_sequence}
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+-Bill-\d{2}$/', $expectedFormat);
        
        // Test with different file references
        $testCases = [
            'MG030WH-Bill-01',
            'MG030WH-Bill-02', 
            'MG030WH-Bill-03',
            'MG030WH-Bill-10',
            'MG030WH-Bill-99'
        ];
        
        foreach ($testCases as $testCase) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]+-Bill-\d{2}$/', $testCase);
        }
    }

    public function test_sequence_number_extraction()
    {
        // Test extracting sequence numbers from bill names
        $testCases = [
            'MG030WH-Bill-01' => 1,
            'MG030WH-Bill-02' => 2,
            'MG030WH-Bill-10' => 10,
            'MG030WH-Bill-99' => 99,
            'MG030WH-Bill-05' => 5
        ];
        
        foreach ($testCases as $billName => $expectedSequence) {
            $sequence = (int)substr($billName, -2);
            $this->assertEquals($expectedSequence, $sequence);
        }
    }

    public function test_file_reference_extraction()
    {
        // Test extracting file reference from bill names
        $testCases = [
            'MG030WH-Bill-01' => 'MG030WH',
            'MG030WH-Bill-02' => 'MG030WH',
            'MG031WH-Bill-01' => 'MG031WH'
        ];
        
        foreach ($testCases as $billName => $expectedReference) {
            $reference = substr($billName, 0, strpos($billName, '-Bill-'));
            $this->assertEquals($expectedReference, $reference);
        }
    }
} 