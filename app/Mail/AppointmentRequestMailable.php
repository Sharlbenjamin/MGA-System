<?php

namespace App\Mail;

use App\Models\File;
use App\Models\ProviderBranch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class AppointmentRequestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $file;
    public $branch;
    public $customEmails;

    public function __construct(File $file, ProviderBranch $branch, array $customEmails = [])
    {
        $this->file = $file;
        $this->branch = $branch;
        $this->customEmails = $customEmails;
    }

    public function build()
    {
        $username = Auth::user()->smtp_username ?? config('mail.from.address');
        $name = Auth::user()->name ?? config('mail.from.name');

        $subject = 'Appointment Request - ' . $this->file->patient->name . ' - ' . $this->file->mga_reference;

        $mailBuilder = $this->view('emails.request-appointment')
            ->from($username, $name)
            ->subject($subject)
            ->with([
                'file' => $this->file,
                'branch' => $this->branch,
                'customEmails' => $this->customEmails
            ]);

        // Add branch email as primary recipient
        if ($this->branch->email) {
            $mailBuilder->to($this->branch->email);
        }

        // Add operation contact email if available
        if ($this->branch->operationContact && $this->branch->operationContact->email) {
            $mailBuilder->cc($this->branch->operationContact->email);
        }

        // Add custom emails as CC
        foreach ($this->customEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mailBuilder->cc($email);
            }
        }

        return $mailBuilder;
    }
}
