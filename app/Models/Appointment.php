<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    static::updated(function ($appointment) {
        if ($appointment->status === 'Confirmed') {
            // Update the file with the confirmed appointment details
            $appointment->file->update([
                'status' => 'In Progress',
                'provider_branch_id' => $appointment->provider_branch_id,
                'service_date' => $appointment->service_date,
                'service_time' => $appointment->service_time,
            ]);

            $appointment->file->comments()->create([
                'user_id' => auth()->id(),
                'content' => "Appointment confirmed with **{$appointment->providerBranch->name}** on **{$appointment->service_date}** at **{$appointment->service_time}**. File updated to 'In Progress'.",
            ]);

            // Cancel all other appointments linked to this file
            $otherAppointments = $appointment->file->appointments()
                ->where('id', '!=', $appointment->id) // Exclude the confirmed appointment
                ->where('status', '!=', 'Cancelled') // Avoid redundant updates
                ->update(['status' => 'Cancelled']);

            if ($otherAppointments > 0) {
                $appointment->file->comments()->create([
                    'user_id' => auth()->id(),
                    'content' => "**{$otherAppointments}** other appointment(s) were automatically cancelled after confirming this one.",
                ]);
            }
        }
    });
}
}