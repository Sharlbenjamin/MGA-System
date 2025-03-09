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
    static::created(function ($appointment) {
        $appointment->file->comments()->create([
            'user_id' => Auth::id(),
            'content' => "New appointment scheduled with **{$appointment->providerBranch->branch_name}** on **{$appointment->service_date}** at **{$appointment->service_time}**.",
        ]);
        // inform the branch
        $appointment->providerBranch->notifyBranch('new', $appointment);
    });

    // When an appointment is updated, check if it's confirmed
    static::updated(function ($appointment) {
        if ($appointment->status === 'Confirmed') {
            // Update the file with the confirmed appointment details
            $appointment->file->update([
                'status' => 'In Progress',
                'provider_branch_id' => $appointment->provider_branch_id,
                'service_date' => $appointment->service_date,
                'service_time' => $appointment->service_time,
            ]);

            //$appointment->file->patient->notifyPatient('confirm', $appointment);
            $appointment->file->patient->client->notifyClient('client_confirm', $appointment->file);
            $appointment->file->patient->notifyPatient('confirm', $appointment->file);
            $appointment->providerBranch?->notifyBranch('confirm_appointment', $appointment);

            $appointment->file->comments()->create([
                'user_id' => Auth::id(),
                'content' => "Appointment confirmed with **{$appointment->providerBranch->branch_name}** on **{$appointment->service_date}** at **{$appointment->service_time}**. File updated to 'In Progress'.",
            ]);

            // Cancel all other appointments linked to this file
            $otherAppointments = $appointment->file->appointments()
                ->where('id', '!=', $appointment->id) // Exclude the confirmed appointment
                ->where('status', '!=', 'Cancelled') // Avoid redundant updates
                ->update(['status' => 'Cancelled']);
        }

        // Notify branch if the appointment was cancelled
        if ($appointment->status === 'Cancelled') {
            $appointment->providerBranch?->notifyBranch('cancel', $appointment);
            //update teh comment of this appointment to reflect the cancellation
            $appointment->file->comments()->where('content', 'like', "%{$appointment->providerBranch->branch_name}%")->update([
                'content' => "Appointment with **{$appointment->providerBranch->branch_name}** on **{$appointment->service_date}** at **{$appointment->service_time}** was cancelled.",
            ]);
        }

        // Notify branch if service date or time was changed
        if ($appointment->wasChanged(['service_date', 'service_time'])) {
            $appointment->providerBranch?->notifyBranch('update', $appointment);
        }
    });
}
}