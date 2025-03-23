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
            if (!$appointment->providerBranch?->primaryContact('Appointment')) {
                return false; // Skip appointment creation if no contact exists
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
                        'service_time' => $appointment->service_time, // Ensure service_time updates if necessary
                        'status' => 'Requested',
                    ]);

                    // Notify the branch of the update
                    $existingAppointment->providerBranch?->notifyBranch('appointment_updated', $existingAppointment);
                }

                return false; // Prevent duplicate creation but allow updates
            }
        });

        static::created(function ($appointment) {
            $appointment->providerBranch?->notifyBranch('appointment_created', $appointment);
            if($appointment->file->status === 'New') {
                $appointment->file->update(['status' => 'Handling']);
            }
        });

        static::updated(function ($appointment) {
            if ($appointment->status === 'Confirmed') {
                $appointment->file->patient->client?->notifyClient('appointment_confirmed_client', $appointment);
                $appointment->file->patient?->notifyPatient('appointment_confirmed_patient', $appointment);
                $appointment->providerBranch?->notifyBranch('appointment_confirmed', $appointment);
                $appointment->file->update(['status' => 'Confirmed']);
                $appointment->file->appointments()->where('id', '!=', $appointment->id)->update(['status' => 'Cancelled']);


            }

            if ($appointment->status === 'Cancelled') {
                $appointment->providerBranch?->notifyBranch('appointment_cancelled', $appointment);
            }

            if ($appointment->wasChanged(['service_date', 'service_time'])) {
                $appointment->providerBranch?->notifyBranch('appointment_updated', $appointment);
            }
        });
    }
}
