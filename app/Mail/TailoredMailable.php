<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class TailoredMailable extends Mailable
{
    use Queueable, SerializesModels;

    protected $customSubject;
    public string $body;
    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $body)
    {
        $this->subject = $subject;
        $this->body = $body;
    }
    public function build()
    {

        $username = Auth::user()->smtp_username;
        $password = Auth::user()->smtp_password;

        return $this->subject($this->subject)
                    ->from($username, Auth::user()->name)
                    ->view('emails.tailored-mail') // Make sure this Blade view exists
                    ->with(['body' => $this->body]);
    }
}
