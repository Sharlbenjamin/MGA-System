<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Appointment;
use App\Models\File;
use App\Models\ProviderBranch;

class AppointmentRequestMail extends Mailable
{
    use SerializesModels;

    public File $file;
    public ?ProviderBranch $providerBranch;
    public ?string $customEmail;

    public function __construct(File $file, ?ProviderBranch $providerBranch = null, ?string $customEmail = null)
    {
        $this->file = $file;
        $this->providerBranch = $providerBranch;
        $this->customEmail = $customEmail;
    }

    public function build()
    {
        $subject = 'Branch Appointment';
        $view = 'emails.appointment_request';
        
        if ($this->providerBranch) {
            $subject .= ' - ' . $this->providerBranch->branch_name;
        } elseif ($this->customEmail) {
            $subject .= ' - Custom Notification';
        }

        return $this->subject($subject)
            ->view($view)
            ->with([
                'file' => $this->file,
                'providerBranch' => $this->providerBranch,
                'customEmail' => $this->customEmail,
                'isCustomEmail' => !is_null($this->customEmail),
            ]);
    }
}