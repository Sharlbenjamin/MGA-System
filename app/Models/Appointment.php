<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = ['file_id', 'provider_branch_id', 'service_date', 'service_time', 'status'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function providerBranch(): BelongsTo
    {
        return $this->belongsTo(ProviderBranch::class);
    }

    protected static function boot()
    {
        parent::boot();

        // When an appointment is created, log a comment and notify the branch
        static::creating(function ($appointment) {
            // Check if provider has email directly, or if branch has operation contact
            $providerHasEmail = $appointment->providerBranch->provider && $appointment->providerBranch->provider->email;
            $branchHasContact = $appointment->providerBranch->primaryContact('Appointment');
            
            if (!$providerHasEmail && !$branchHasContact) {
                return false; // Skip appointment creation if no email contact exists
            }

            // Check if an appointment already exists for this branch and date
            $existingAppointment = Appointment::where('provider_branch_id', $appointment->provider_branch_id)
                ->where('file_id', $appointment->file_id)
                ->first();

            if ($existingAppointment) {
                // If the service date has changed, update it
                if ($existingAppointment->service_date !== $appointment->service_date) {
                    $existingAppointment->update([
                        'service_date' => $appointment->service_date,
                        'service_time' => $appointment->service_time,
                        'status' => 'Requested',
                    ]);

                    // Notify the branch of the update
                    $existingAppointment->providerBranch->notifyBranch('appointment_updated', $existingAppointment);
                }

                return false; // Prevent duplicate creation but allow updates
            }
        });

        static::created(function ($appointment) {
            $appointment->providerBranch->notifyBranch('appointment_created', $appointment);
            if($appointment->file->status === 'New') {
                $appointment->file->update(['status' => 'Handling']);
            }
        });

        static::updated(function ($appointment) {
            if ($appointment->status === 'Confirmed') {
                // Ensure immediate update of file fields with appointment data
                $appointment->file->update([
                    'status' => 'Confirmed',
                    'service_date' => $appointment->service_date,
                    'service_time' => $appointment->service_time,
                    'provider_branch_id' => $appointment->provider_branch_id,
                ]);
                
                // Cancel all other appointments for this file
                $appointment->file->appointments()->where('id', '!=', $appointment->id)->update(['status' => 'Cancelled']);

                // Generate Google Meet link for telemedicine appointments (service_type_id = 2)
                if ($appointment->file->service_type_id == 2) {
                    $appointment->file->generateGoogleMeetLink();
                }

                // Send notifications based on contact preference
                if($appointment->file->contact_patient === 'Client'){
                    $appointment->file->patient->client->notifyClient('appointment_confirmed', $appointment, '');
                }else{
                    $appointment->file->patient->notifyPatient('appointment_confirmed', $appointment);
                }
                
                // Notify the provider branch
                $appointment->providerBranch->notifyBranch('appointment_confirmed', $appointment);
            }

            if ($appointment->status === 'Cancelled') {
                // check the previous status
                $previousStatus = $appointment->getOriginal('status');
                if ($previousStatus === 'Available') {
                    $appointment->providerBranch->notifyBranch('appointment_cancelled', $appointment);
                }
            }

            if ($appointment->wasChanged(['service_date', 'service_time'])) {
                $appointment->providerBranch->notifyBranch('appointment_updated', $appointment);
            }
        });
    }
}
