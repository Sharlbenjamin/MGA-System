<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bill;
use App\Models\File;
use App\Models\Patient;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bill_sequence_generation()
    {
        // Create a client
        $client = Client::factory()->create([
            'name' => 'Test Client',
            'initials' => 'TC'
        ]);

        // Create a patient
        $patient = Patient::factory()->create([
            'name' => 'John Doe',
            'client_id' => $client->id
        ]);

        // Create a file
        $file = File::factory()->create([
            'mga_reference' => 'MGA-2024-001',
            'patient_id' => $patient->id
        ]);

        // Create first bill
        $bill1 = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => null // Let it auto-generate
        ]);

        // Create second bill
        $bill2 = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => null // Let it auto-generate
        ]);

        // Create third bill
        $bill3 = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => null // Let it auto-generate
        ]);

        // Refresh models to get the generated names
        $bill1->refresh();
        $bill2->refresh();
        $bill3->refresh();

        // Assert the sequence is correct
        $this->assertEquals('MGA-2024-001-Bill-001', $bill1->name);
        $this->assertEquals('MGA-2024-001-Bill-002', $bill2->name);
        $this->assertEquals('MGA-2024-001-Bill-003', $bill3->name);
    }

    public function test_bill_sequence_with_different_files()
    {
        // Create a client
        $client = Client::factory()->create([
            'name' => 'Test Client',
            'initials' => 'TC'
        ]);

        // Create a patient
        $patient = Patient::factory()->create([
            'name' => 'John Doe',
            'client_id' => $client->id
        ]);

        // Create two different files
        $file1 = File::factory()->create([
            'mga_reference' => 'MGA-2024-001',
            'patient_id' => $patient->id
        ]);

        $file2 = File::factory()->create([
            'mga_reference' => 'MGA-2024-002',
            'patient_id' => $patient->id
        ]);

        // Create bills for different files
        $bill1 = Bill::factory()->create([
            'file_id' => $file1->id,
            'name' => null
        ]);

        $bill2 = Bill::factory()->create([
            'file_id' => $file2->id,
            'name' => null
        ]);

        $bill3 = Bill::factory()->create([
            'file_id' => $file1->id,
            'name' => null
        ]);

        // Refresh models
        $bill1->refresh();
        $bill2->refresh();
        $bill3->refresh();

        // Assert each file has its own sequence
        $this->assertEquals('MGA-2024-001-Bill-001', $bill1->name);
        $this->assertEquals('MGA-2024-002-Bill-001', $bill2->name);
        $this->assertEquals('MGA-2024-001-Bill-002', $bill3->name);
    }

    public function test_bill_sequence_with_existing_bills()
    {
        // Create a client
        $client = Client::factory()->create([
            'name' => 'Test Client',
            'initials' => 'TC'
        ]);

        // Create a patient
        $patient = Patient::factory()->create([
            'name' => 'John Doe',
            'client_id' => $client->id
        ]);

        // Create a file
        $file = File::factory()->create([
            'mga_reference' => 'MGA-2024-001',
            'patient_id' => $patient->id
        ]);

        // Create bills with existing names
        $existingBill1 = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => 'MGA-2024-001-Bill-001'
        ]);

        $existingBill2 = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => 'MGA-2024-001-Bill-003'
        ]);

        // Create a new bill (should get sequence 004)
        $newBill = Bill::factory()->create([
            'file_id' => $file->id,
            'name' => null
        ]);

        $newBill->refresh();

        // Assert the new bill gets the next available sequence
        $this->assertEquals('MGA-2024-001-Bill-004', $newBill->name);
    }
} 