<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\File;

class MeetingLinkCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $file;
    public $meetLink;

    public function __construct(File $file, string $meetLink)
    {
        $this->file = $file;
        $this->meetLink = $meetLink;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->markdown('emails.meeting-link-created')
            ->subject("Telemedicine Meeting Link - {$this->file->patient->name}");
    }
}
