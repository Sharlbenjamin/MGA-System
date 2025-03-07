<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Appointment;

class AppointmentRequestMail extends Mailable
{
    use SerializesModels;

    public Appointment $appointment;

    public function __construct(Appointment $appointment) // ✅ Ensure this expects an Appointment model
    {
        $this->appointment = $appointment;
    }

    public function build()
    {
        return $this->subject('New Appointment Request')
            ->view('emails.appointment_request')
            ->with([
                'providerBranch' => $this->appointment->providerBranch->name,
                'serviceDate' => $this->appointment->service_date,
            ]);
    }
}