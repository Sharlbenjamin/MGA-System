<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Client;
use App\Models\ServiceType;
use App\Models\ProviderBranch;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;


class TelemedicineConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a user for authentication
        $this->user = User::factory()->create();
        
        // Create service types
        ServiceType::create(['id' => 1, 'name' => 'House Call']);
        ServiceType::create(['id' => 2, 'name' => 'Telemedicine']);
        
        // Create a client
        $this->client = Client::factory()->create();
        
        // Create a patient
        $this->patient = Patient::factory()->create([
            'client_id' => $this->client->id
        ]);
        
        // Create a provider
        $this->provider = Provider::factory()->create();
        
        // Create a provider branch
        $this->providerBranch = ProviderBranch::factory()->create([
            'provider_id' => $this->provider->id,
            'service_types' => ['Telemedicine']
        ]);
    }

    public function test_telemedicine_confirmation_button_visibility()
    {
        // Create a telemedicine file with requested appointment
        $file = File::factory()->create([
            'patient_id' => $this->patient->id,
            'service_type_id' => 2, // Telemedicine
            'status' => 'Handling'
        ]);

        $appointment = Appointment::factory()->create([
            'file_id' => $file->id,
            'provider_branch_id' => $this->providerBranch->id,
            'status' => 'Requested'
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('filament.admin.resources.files.view', $file));

        $response->assertStatus(200);
        // The button should be visible for telemedicine files with requested appointments
    }

    public function test_telemedicine_confirmation_functionality()
    {
        Mail::fake();

        // Create a telemedicine file
        $file = File::factory()->create([
            'patient_id' => $this->patient->id,
            'service_type_id' => 2, // Telemedicine
            'status' => 'Handling',
            'service_date' => null,
            'service_time' => null,
            'provider_branch_id' => null
        ]);

        // Create a requested appointment
        $appointment = Appointment::factory()->create([
            'file_id' => $file->id,
            'provider_branch_id' => $this->providerBranch->id,
            'status' => 'Requested',
            'service_date' => '2024-01-15',
            'service_time' => '14:30:00'
        ]);

        // Test the confirmTelemedicineAppointment method
        $confirmedAppointment = $file->confirmTelemedicineAppointment();

        // Refresh the file from database
        $file->refresh();

        // Assertions
        $this->assertEquals('Confirmed', $confirmedAppointment->status);
        $this->assertEquals('Confirmed', $file->status);
        $this->assertEquals('2024-01-15', $file->service_date);
        $this->assertEquals('14:30:00', $file->service_time);
        $this->assertEquals($this->providerBranch->id, $file->provider_branch_id);

        // Check that a comment was created
        $this->assertDatabaseHas('comments', [
            'file_id' => $file->id,
            'user_id' => $this->user->id,
            'content' => 'Telemedicine appointment confirmed manually via "Confirm Telemedicine" button.'
        ]);
    }

    public function test_telemedicine_confirmation_without_requested_appointment()
    {
        // Create a telemedicine file without any requested appointments
        $file = File::factory()->create([
            'patient_id' => $this->patient->id,
            'service_type_id' => 2, // Telemedicine
            'status' => 'Handling'
        ]);

        // Test that an exception is thrown when no requested appointment exists
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No requested appointment found for this file.');

        $file->confirmTelemedicineAppointment();
    }

    public function test_appointment_confirmation_updates_file_fields()
    {
        Mail::fake();

        // Create a telemedicine file
        $file = File::factory()->create([
            'patient_id' => $this->patient->id,
            'service_type_id' => 2, // Telemedicine
            'status' => 'Handling',
            'service_date' => null,
            'service_time' => null,
            'provider_branch_id' => null
        ]);

        // Create a requested appointment
        $appointment = Appointment::factory()->create([
            'file_id' => $file->id,
            'provider_branch_id' => $this->providerBranch->id,
            'status' => 'Requested',
            'service_date' => '2024-01-15',
            'service_time' => '14:30:00'
        ]);

        // Directly update the appointment status to trigger the event
        $appointment->update(['status' => 'Confirmed']);

        // Refresh the file from database
        $file->refresh();

        // Assertions
        $this->assertEquals('Confirmed', $file->status);
        $this->assertEquals('2024-01-15', $file->service_date);
        $this->assertEquals('14:30:00', $file->service_time);
        $this->assertEquals($this->providerBranch->id, $file->provider_branch_id);
    }
} 